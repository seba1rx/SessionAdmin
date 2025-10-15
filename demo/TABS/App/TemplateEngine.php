<?php

namespace App;

class TemplateEngine
{
    public static function render(string $name, mixed $context = []): string
    {
        try{
            // starts the buffer
            ob_start();

            // adds the vars to the buffer scope
            foreach ($context as $name => $value) {
                ${$name} = $value;
            }

            // adds the template to the buffer in order to render the content
            include $name;

            // gets the rendered contents as a string from the buffer, end buffer
            $html_content = ob_get_clean();

            return $html_content;

        }catch(\Exception $e){

            throw new \Exception("An error occurred while processing the template {$name}");
        }
    }
}