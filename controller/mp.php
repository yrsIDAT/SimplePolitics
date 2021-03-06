<?php

class MP
{
    public function __construct($mapper)
    {
        $mapper->map('fromcoords')->setMinParams(1);
        $mapper->map('frompostcode')->setMinParams(1);
        $mapper->map('fromname')->setMinParams(1);
        $mapper->map('profile')->setMinParams(1);
        $mapper->map('contact')->setMinParams(1);
        $mapper->map('search')->setMinParams(1);
    }

    public function search($type, $value = null)
    {
        echo Template::getTemplate('mp:search')->parse(array(
            'coords' => $type === 'auto',
            'postcode' => $type === 'postcode',
            'name' => $type === 'name',
            'value' => $value)
        );
    }

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
        if ($lat + $lon === (float) 0 && !DEMO_MODE) {
            http_response_code(400);
            die("Cannot get location data");
        }
        $json = @json_decode(@file_get_contents("http://uk-postcodes.com/latlng/$lat,$lon.json"));
        if (($json == null || isset($json->error)) && !DEMO_MODE) {
            http_response_code(404);
            die("Cannot find postcode for your location");
        }
        if (DEMO_MODE) {
            $json->postcode = 'PL4 6DX';
        }
        return $this->frompostcode($json->postcode);
    }

    public function frompostcode($postcode)
    {
        $twfy = $this->libraries->load('TWFYAPI', TWFY_KEY);
        $mp = @json_decode($twfy->query('getMP', array("postcode" => $postcode, "output" => "js")));
        if ($mp == null || !isset($mp->person_id)) {
            http_response_code(404);
            die("Could not find MP from postcode ($postcode)");
        }
        echo $mp->person_id;
    }

    private function getLocationHeader($url)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        $response = curl_exec($ch);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        $header = substr($response, 0, $header_size);
        $h = 'Location: ';
        $start = strpos($header, $h) + strlen($h);
        $end = strpos($header, "\r\n", $start);
        return substr($header, $start, $end - $start);
    }

    public function profile($personID)
    {
        $twfy = $this->libraries->load('TWFYAPI', TWFY_KEY);
        $mp = json_decode($twfy->query('getMP', array('id' => $personID, 'output' => 'js')))[0];

        // Library doesn't have support for the function
        $area = $this->getLocationHeader("http://www.theyworkforyou.com/api/getBoundary?key=" . TWFY_KEY . "&name=" . urlencode($mp->constituency) . "&output=xml");
        $debatesData = json_decode($twfy->query('getDebates', array('person' => $personID, 'num' => 4, 'output' => 'js', 'type' => 'commons')));
        $debates = array();
        foreach ($debatesData->rows as $debate) {
            $debates[] = array(
                'summary' => $debate->extract,
                'topic' => $debate->parent->body,
                'date' => date('l, j F H:m', strtotime($debate->hdate . ' ' . $debate->htime)),
                'gid' => $this->getDebateGid($debate->listurl));
        }
        echo Template::getTemplate('mp:profile')->parse(array(
            'personID' => $personID,
            'location' => $mp->constituency,
            'name' => $mp->full_name,
            'image' => array('src' => $mp->image, 'width' => $mp->image_width, 'height' => $mp->image_height),
            'debates' => $debates,
            'partyColor' => $this->getColorFromParty($mp->party),
            'area' => $area
        ));
    }

    private function getDebateGid($listURL)
    {
        parse_str(parse_url($listURL)['query'], $query);
        return $query['id'];
    }

    private function getColorFromParty($partyName)
    {
        // Thanks to http://blog.richardallen.co.uk/uk-political-party-web-colours/
        switch ($partyName) {
            case 'Conservative':
            case 'Unionist':
                return '#0087dc';
            case 'Liberal Democrat':
                return '#FDBB30';
            case 'Labour':
                return '#d50000';
            case 'Scottish National Party':
                return '#FFF95D';
            case 'Plaid Cymru':
                return '#3F8428';
            case 'Green Party':
                return '#008066';
            case 'UKIP':
                return '#EFE600';
            default:
                return 'white';
        }
    }

    public function contact($name)
    {
        $mpInfo = json_decode(file_get_contents("http://findyourmp.parliament.uk/api/search?q=" . urlencode($name) . "&f=js"));
        $mpInfo = $mpInfo === null ? null : $mpInfo->results->members;
        $mpInfo = $mpInfo === null || count($mpInfo) === 0 ? null : $mpInfo[0];
        if ($mpInfo == null || !isset($mpInfo->member_biography_url) || !$mpInfo->member_biography_url) {
            die("Unable to find contact details for {$name}");
        }
        header("Location: {$mpInfo->member_biography_url}");
    }

    public function fromname($name)
    {
        $twfy = $this->libraries->load('TWFYAPI', TWFY_KEY);
        $mps = @json_decode($twfy->query('getMPs', array("search" => $name, "output" => "js")));
        if ($mps === null || count($mps) === 0) {
            http_response_code(404);
            die("Could not find MP from name");
        }
        echo $mps[0]->person_id;
    }

    public function redirectProfile($memberID)
    {
        $twfy = $this->libraries->load('TWFYAPI', TWFY_KEY);
        $pageURL = $this->getLocationHeader("http://www.theyworkforyou.com/mp/?m={$memberID}");
        if (preg_match('#/mp/(\d+)/#', $pageURL, $matches)) {
            header("Location: /mp/profile/{$matches[1]}");
            exit;
        }
        die("Could not find profile");
    }
}
