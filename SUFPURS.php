<?php

namespace SuFPURS;

class SuFPURS
{
    private $_url = null;
    private $_original_data = null;
    private $_results = [];

    public function __construct($url)
    {
        $this->_url = $url;
    }

    public function run()
    {
        $this->validateUrl();
        $this->fetFromUrl();
        $this->parseOriginalData();
    }

    public function getResults()
    {
        return $this->_results;
    }

    private function validateUrl()
    {
        if(!$this->str_starts_with($this->_url, 'https://suf.purs.gov.rs/v/?vl='))
        {
            throw new \Exception('invalid url');
        }
    }

    private function fetFromUrl()
    {
        /**
         * unable to perform successfull fetch using
         * curl or file_get_contents
         */
        $this->_original_data = shell_exec('curl '.$this->_url.' 2>&1');

        if(strpos($this->_original_data, 'Рачун је проверен') === false)
        {
            throw new \Exception('racun nije proveren');
        }
    }

    private function parseOriginalData()
    {
        $racun_start_tag = '============ ФИСКАЛНИ РАЧУН ============';
        $racun_end_tag = '======== КРАЈ ФИСКАЛНОГ РАЧУНА =========';

        $racun_start_tag_strpos = strpos($this->_original_data, $racun_start_tag);
        if($racun_start_tag_strpos === false)
        {
            exit('nema start tag');
        }
        if(strpos($this->_original_data, $racun_end_tag) === false)
        {
            exit('nema end tag');
        }

        $racun = substr($this->_original_data, $racun_start_tag_strpos);
        $racun = substr($racun, 0, strpos($racun, $racun_end_tag));

        $qr_tag = '<br/><img src=data:image/gif;base64,';
        if(strpos($racun, $qr_tag) === false)
        {
            exit('nema qr tag');
        }
        $racun = substr($racun, 0, strpos($racun, $qr_tag));

        $bill_split = preg_split("/\r\n|\r|\n/", $racun);

        $shop = $bill_split[2];
        $cashier = null;
        $articles = [];
        $total_amount = null;
        $bill_time = null;
        $articles_started = false;
        for($i=0; $i<count($bill_split); $i++)
        {
            $bill_part = $bill_split[$i];

            if($articles_started)
            {
                if(trim($bill_part) === '----------------------------------------')
                {
                    $articles_started = false;
                }
                else
                {
                    $article_line1 = $bill_part;
                    $article_line2 = $bill_split[++$i];
                    $article_line2 = preg_replace('!\s+!', ' ', trim($article_line2));
                    $article_line2_split = explode(' ', $article_line2);

                    $article_name = trim($article_line1);
                    $article_single_price = $article_line2_split[0];
                    $article_amount = $article_line2_split[1];
                    $article_total_price = $article_line2_split[2];

                    $article = [
                        'name' => $article_name,
                        'single_price' => $article_single_price,
                        'amount' => $article_amount,
                        'total_price' => $article_total_price
                    ];

                    $article_metadata = $this->getArticleMetadata($shop, $article);

                    $article['metadata'] = $article_metadata;

                    $articles[] = $article;
                }
            }
            else if($this->str_starts_with($bill_part, 'Касир:'))
            {
                $cashier = trim(substr($bill_part, strlen('Касир:')));
            }
            else if(trim($bill_part) === 'Артикли')
            {
                $articles_started = true;

                /**
                 * next lines are
                 *  '========================================'
                 *  'Назив   Цена         Кол.         Укупно'
                 */
                $i += 2; /// 
            }
            else if($this->str_starts_with($bill_part, 'Укупан износ:'))
            {
                $total_amount = trim(substr($bill_part, strlen('Укупан износ:')));
            }
            else if($this->str_starts_with($bill_part, 'ПФР време:'))
            {
                $bill_time = trim(substr($bill_part, strlen('ПФР време:')));
            }
        }

        $this->_results = [
            'shop' => $shop,
            'cashier' => $cashier,
            'articles' => $articles,
            'total_amount' => $total_amount,
            'bill_time' => $bill_time,
        ];
    }

    private function str_starts_with($haystack, $needle)
    {
        return substr($haystack, 0, strlen($needle)) === $needle;
    }

    private function str_contains($haystack, $needle)
    {
        return strpos($haystack, $needle) !== false;
    }

    private function getArticleMetadata($shop, $article)
    {
        $result = [];

        if($shop === 'DELHAIZE SERBIA')
        {
            if($this->str_starts_with($article['name'], 'Hleb'))
            {
                $result[] = 'food';
                $result[] = 'bread';
            }
            else if($this->str_starts_with($article['name'], 'Pivo'))
            {
                $result[] = 'drink';
                $result[] = 'beer';
            }
            else if($this->str_starts_with($article['name'], 'Next Classic breskva'))
            {
                $result[] = 'drink';
                $result[] = 'juce';
            }
        }
        else if($shop === 'PEPCO')
        {
            if($this->str_contains($article['name'], 'vlažnih mar'))
            {
                $result[] = 'cleaning';
                $result[] = 'wetwipes';
            }
            else if($this->str_starts_with($article['name'], 'poklon kesa'))
            {
                $result[] = 'bag';
            }
            else if($this->str_starts_with($article['name'], 'puzzle'))
            {
                $result[] = 'game';
                $result[] = 'puzzle';
            }
        }
        else if($shop === 'OMV SRBIJA DOO BEOGRAD')
        {
            if($this->str_starts_with($article['name'], 'Espresso'))
            {
                $result[] = 'drink';
                $result[] = 'coffee';
            }
            else if($this->str_contains($article['name'], 'krofna'))
            {
                $result[] = 'food';
                $result[] = 'donut';
            }
            else if($this->str_contains($article['name'], 'EVRO DIZEL'))
            {
                $result[] = 'car';
                $result[] = 'gass';
            }
        }
        else if($shop === 'dm drogerie markt')
        {
            if($this->str_starts_with($article['name'], 'Profissimo kese'))
            {
                $result[] = 'bag';
                $result[] = 'trashbag';
            }
            else if($this->str_starts_with($article['name'], 'ziaja sun los'))
            {
                $result[] = 'body';
                $result[] = 'suncare';
            }
            else if($this->str_starts_with($article['name'], 'AJAX FLOR'))
            {
                $result[] = 'cleaning';
                $result[] = 'floor';
            }
        }

        return $result;
    }
}