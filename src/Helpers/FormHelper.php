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
            $html .= "<label for='$name' class='text-sm font-semibold text-gray-700'>$label</label>";
            $html .= "<input type='$type' name='$name' id='$name' value='" . htmlspecialchars($value) . "' class='w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition duration-150 " . (isset($errors[$name]) ? 'border-red-500' : 'border-gray-300') . "'>";
            if (isset($errors[$name])) {
                $html .= "<span class='text-red-600 text-xs font-medium'>{$errors[$name]}</span>";
            }
            $html .= "</div>";
        }
        $html .= "<button type='submit' class='w-full bg-green-700 hover:bg-green-800 text-white font-bold py-2 px-4 rounded-lg transition duration-150'>Submit</button>";
        $html .= "</form>";
        return $html;
    }
}
