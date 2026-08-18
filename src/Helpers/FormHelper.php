<?php

class FormHelper {
    public static function generateForm($fields, $action, $method = 'POST') {
        $html = "<form action='$action' method='$method' class='space-y-4'>";
        foreach ($fields as $field) {
            $name = $field['name'];
            $label = $field['label'];
            $type = $field['type'] ?? 'text';
            $value = $field['value'] ?? '';
            
            $html .= "<div class='flex flex-col'>";
            $html .= "<label for='$name' class='text-sm font-medium text-gray-700'>$label</label>";
            $html .= "<input type='$type' name='$name' id='$name' value='$value' class='mt-1 p-2 border rounded-md'>";
            $html .= "</div>";
        }
        $html .= "<button type='submit' class='bg-green-600 text-white p-2 rounded-md'>Submit</button>";
        $html .= "</form>";
        return $html;
    }
}
