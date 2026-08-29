<?php
/**
 * ChatMemory — persistent, per-user chat history for the AI assistant.
 *
 * Stores every Q&A exchange so users can review prior conversations from the
 * assistant's "Memory" tabs. Fully scoped to the logged-in user: sessions and
 * messages are only ever readable/writable by their owner.
 */
class ChatMemory {

    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /** List the user's conversation sessions, newest first. @return array */
    public function sessions($userId) {
        $userId = (int) $userId;
        $stmt = $this->pdo->prepare("
            SELECT s.id, s.title, s.created_at, s.updated_at,
                   COUNT(m.id)                                  AS message_count,
                   (SELECT message FROM chat_messages lm
                     WHERE lm.session_id = s.id AND lm.role = 'assistant'
                     ORDER BY lm.id DESC LIMIT 1)               AS last_message
            FROM chat_sessions s
            LEFT JOIN chat_messages m ON m.session_id = s.id
            WHERE s.user_id = ?
            GROUP BY s.id
            ORDER BY s.updated_at DESC, s.id DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /** Create a new session for the user. @return int new session id */
    public function createSession($userId, $title = 'New chat') {
        $stmt = $this->pdo->prepare("INSERT INTO chat_sessions (user_id, title) VALUES (?, ?) RETURNING id");
        $stmt->execute([(int) $userId, $title ?: 'New chat']);
        return (int) $stmt->fetchColumn();
    }

    /** Full message history of a session, only if it belongs to the user. @return array|null */
    public function history($sessionId, $userId) {
        $stmt = $this->pdo->prepare("SELECT id, user_id, title FROM chat_sessions WHERE id = ? AND user_id = ?");
        $stmt->execute([(int) $sessionId, (int) $userId]);
        $session = $stmt->fetch();
        if (!$session) return null;

        $rows = $this->pdo->prepare("SELECT role, message, cards_json, created_at FROM chat_messages WHERE session_id = ? ORDER BY id ASC");
        $rows->execute([(int) $sessionId]);
        return [
            'session'  => $session,
            'messages' => array_map(function ($m) {
                return [
                    'role'    => $m['role'],
                    'text'    => $m['message'],
                    'cards'   => $m['cards_json'] ? (json_decode($m['cards_json'], true) ?: []) : [],
                    'created' => $m['created_at'],
                ];
            }, $rows->fetchAll()),
        ];
    }

    /** Append a message to a session (only if owned by the user). */
    public function append($sessionId, $userId, $role, $message, $cards = []) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM chat_sessions WHERE id = ? AND user_id = ?");
        $stmt->execute([(int) $sessionId, (int) $userId]);
        if ((int) $stmt->fetchColumn() === 0) return false;

        $ins = $this->pdo->prepare("INSERT INTO chat_messages (session_id, role, message, cards_json) VALUES (?, ?, ?, ?)");
        $ins->execute([(int) $sessionId, $role, $message, $cards ? json_encode($cards) : null]);

        $up = $this->pdo->prepare("UPDATE chat_sessions SET updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $up->execute([(int) $sessionId]);
        return true;
    }

    /** Auto-generate a short title from the first user message. */
    public function makeTitle($message) {
        $text = trim($message);
        $text = preg_replace('/\s+/', ' ', $text);
        $fnLen = function_exists('mb_strlen') ? 'mb_strlen' : 'strlen';
        $fnSub = function_exists('mb_substr') ? 'mb_substr' : 'substr';
        return $fnLen($text) > 48 ? $fnSub($text, 0, 48) . '…' : $text;
    }
}
