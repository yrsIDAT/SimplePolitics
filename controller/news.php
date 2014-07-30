<?php

class News
{
    public function index()
    {
        echo Template::getTemplate('news:news')->parse();
    }
}
