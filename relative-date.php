<?php

field::$methods['relative'] = function($field, $gran = false) {

    /* Checking if Kirby language config is enabled */
    if (count(site()->languages()) < 1)
        $locale = c::get('relativedate.default', 'en');
    else
        $locale = site()->language()->code();

    $locale = 'ru';

    /* Checking if current language is supported */
    if (!file_exists(__DIR__.'/lang/'.$locale.'.php'))
        $locale = c::get('relativedate.default', 'en');

    /* Getting the language array */
    $language = require 'lang/'.$locale.'.php';

    /* Fallback to global length config if not provided */
    if ($gran == false) $gran = c::get('relativedate.length', 2);

    $time = $field->page->date(false, $field->key);
    $field->value = ftime($time, $language, $gran);
    return $field;
};

function fTime($time, $language, $gran) {
    $now = time();
    $diff = ($now-$time);

    /* Relative mode: $time is past or future? */
    $mode = ($diff > 0) ? 'before_now' : 'after_now';

    /* Setting up mode-sesitive languages */
    if (array_key_exists('lang_'.$mode, $language))
        $words = $language['lang_'.$mode];
    else
        $words = $language;

    /* Linking language variables to respective calculation elements */
    $d[0] = array_merge( array(1),        $words['sec'] );
    $d[1] = array_merge( array(60),       $words['min'] );
    $d[2] = array_merge( array(3600),     $words['h'] );
    $d[3] = array_merge( array(86400),    $words['d'] );
    $d[4] = array_merge( array(604800),   $words['w'] );
    $d[5] = array_merge( array(2592000),  $words['m'] );
    $d[6] = array_merge( array(31104000), $words['y'] );


    /* Calculating relative elements */
    $dateEl = array();
    $phrase = "";
    $secondsLeft = $diff;
    $stopat = 0;
    $elements = 0;
    for($i=6; $i>0; $i--)
    {
         $dateEl[$i] = intval($secondsLeft/$d[$i][0]);
         $secondsLeft -= ($dateEl[$i] * $d[$i][0]);
         if($dateEl[$i]!=0)
         {
            /* only has one form */
            if (count($d[$i]) == 2) :
                $string = $d[$i][1]." ";

            /* simple singular/plural */
            elseif (count($d[$i]) == 3 && !is_array($d[$i][1])) :
                if($dateEl[$i]>1)
                    $string = $d[$i][2]." ";
                else
                    $string = $d[$i][1]." ";

            /* plurals with specific rules */
            else:
                foreach (array_slice($d[$i],1) as $term) :
                    if (is_array($term)) {
                        $condition = 'return '.str_replace('|:n|', $dateEl[$i], $term[0]).';';
                        if (eval($condition)) {
                            $string = $term[1]." ";
                        }
                    }
                endforeach;
            endif;

            $phrase.= str_replace('|:count|', abs($dateEl[$i]), $string);

            $elements++;
            if ($elements >= $gran) break;
         }
    }

    /* Adding prefix or suffix */
    $return = str_replace('|:phrase|', $phrase, $language['meta'][$mode]);

    /* Making relative phrase fuzzy */
    $fuzzy = c::get('relativedate.fuzzy', array());
    if (count($fuzzy) > 0 &&
        array_key_exists('fuzzy', $language)) :
        foreach ($fuzzy as $fuzzyRule) :
            if (array_key_exists($fuzzyRule,$language['fuzzy'][$mode]))
                $return = preg_replace($language['fuzzy'][$mode][$fuzzyRule], $fuzzyRule, $return);
        endforeach;
    endif;

    return $return;
}
