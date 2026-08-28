<?php
require_once __DIR__ . '/../../Intelligence/InsightsEngine.php';

/**
 * InsightsController — backs the Insights (AI/BI) module.
 *
 * A lightweight controller that hands off to the InsightsEngine so the feed
 * page, contextual module cards, and the assistant all read from one source
 * of truth. The heavy lifting (data queries / recommendation logic) lives in
 * InsightsEngine + Assistant.
 */
class InsightsController {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /** @return array feed/BI context for the Insights landing page. */
    public function index() {
        $engine = new InsightsEngine($this->pdo);
        return [
            'recommendations' => $engine->all(),
            'stats'           => $engine->stats(),
        ];
    }
}
