<?php

class MP
{
    public function find()
    {
        echo Template::getTemplate('mp:find')->parse();
    }

    public function fromcoords($coords)
    {
        $lat = $lon = (float) 0;
        if (preg_match('/\(([\d.-]+),([\d.-]+)\)/', $coords, $matches)) {
            $lat = (float) $matches[1];
            $lon = (float) $matches[2];
        }
        if ($lat + $lon === (float) 0) {
            die("Cannot get location data");
        }
        $postcode = json_decode(file_get_contents("http://uk-postcodes.com/latlng/$lat,$lon.json"))->postcode;
        $twfy = $this->libraries->load('TWFYAPI', TWFY_KEY);
        var_dump(json_decode($twfy->query('getMP', array("postcode" => $postcode, "output" => "js"))));

    }

    public function profile()
    {
        echo Template::getTemplate('mp:profile')->parse();
    }
}
