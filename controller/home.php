<?php

class Home
{
    public function index()
    {
        echo Template::getTemplate('home:index')->parse();
    }
}