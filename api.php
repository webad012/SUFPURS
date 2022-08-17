<?php

header('Content-Type: application/json; charset=utf-8');

try
{
    $url = filter_input(INPUT_POST, 'url');
    // $url = filter_input(INPUT_GET, 'url');

    if(empty($url))
    {
        if( empty($_POST) )
        {
            $php_input_obj = json_decode(file_get_contents('php://input'));
            if(property_exists($php_input_obj, 'url'))
            {
                $url = $php_input_obj->url;
            }
        }
        else if(isset($_POST['url']))
        {
            $url = $_POST['url'];
        }
    }

    if(empty($url))
    {
        echo json_encode(['error' => 'empty url param']);
        exit();
    }

    require_once(__DIR__.'/SUFPURS.php');

    $sufpurs = new \SuFPURS\SuFPURS($url);
    $sufpurs->run();
    $bill_data = $sufpurs->getResults();

    echo json_encode($bill_data);
}
catch(\Exception $e)
{
    echo json_encode(['error' => $e->getMessage()]);
}

exit();
