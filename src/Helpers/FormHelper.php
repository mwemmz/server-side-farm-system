<?php

class FormHelper {
    public static function generateForm($fields, $action, $method = 'POST', $errors = []) {
        $html = "<form action='$action' method='$method' class='space-y-6'>";
        foreach ($fields as $field) {
            $name = $field['name'];
            $label = $field['label'];
            $type = $field['type'] ?? 'text';
            $value = $field['value'] ?? '';
            
            $html .= "<div class='flex flex-col gap-2'>";
            $html .= "<label for='$name' class='text-sm font-semibold text-slate-700'>$label</label>";
            $base = "class='w-full px-4 py-2.5 text-slate-800 bg-white/70 border rounded-lg shadow-sm focus:ring-2 focus:ring-green-500 focus:border-green-500 focus:bg-white transition duration-150 placeholder:text-slate-400 " . (isset($errors[$name]) ? 'border-red-400' : 'border-slate-200') . "'";
            if ($type === 'textarea') {
                // Content is placed inside the textarea, so echo the decoded value directly
                $content = htmlspecialchars_decode($value);
                $html .= "<textarea name='$name' id='$name' rows='3' $base>" . htmlspecialchars($content) . "</textarea>";
            } else {
                $html .= "<input type='$type' name='$name' id='$name' value='" . htmlspecialchars($value) . "' autocomplete='off' $base>";
            }
            if (isset($errors[$name])) {
                $html .= "<span class='text-red-600 text-xs font-medium'>{$errors[$name]}</span>";
            }
            $html .= "</div>";
        }
        $html .= "<button type='submit' class='w-full bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-500 hover:to-emerald-500 text-white font-bold py-2.5 px-4 rounded-lg shadow-lg shadow-green-900/30 transition duration-150'>Submit</button>";
        $html .= "</form>";
        return $html;
    }
}
