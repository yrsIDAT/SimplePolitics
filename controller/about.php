<?php

class About
{
    public function politics()
    {
        echo Template::getTemplate('about:politics')->parse();
    }
}
