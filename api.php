<?php

try
{
    $url = filter_input(INPUT_POST, 'url');
    if(empty($url))
    {
        exit('empty url param');
    }

    require_once(__DIR__.'/SUFPURS.php');

    $sufpurs = new \SuFPURS\SuFPURS($url);
    $sufpurs->run();
    $bill_data = $sufpurs->getResults();

    echo json_encode($bill_data);
}
catch(\Exception $e)
{
    exit($e->getMessage());
}

echo 'test';