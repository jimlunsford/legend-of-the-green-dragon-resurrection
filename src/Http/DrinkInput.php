<?php
declare(strict_types=1);
namespace Resurrection\Http;

/** Typed allowlist for the shipped Drinks editor, independent of posted field names. */
final class DrinkInput
{
    /** @param array<string,mixed> $post
     * @return array<string,int|string>
     */
    public static function parse(array $post): array
    {
        $text=['name'=>25,'remarks'=>6000,'buffname'=>50,'buffroundmsg'=>75,'buffwearoff'=>75,'buffeffectfailmsg'=>255,'buffeffectnodmgmsg'=>255,'buffeffectmsg'=>255];
        $ranges=['costperlevel'=>[0,2147483647],'hpchance'=>[0,10],'turnchance'=>[0,10],
            'alwayshp'=>[0,1],'alwaysturn'=>[0,1],'drunkeness'=>[0,100],'harddrink'=>[0,1],
            'hpmin'=>[-20,20],'hpmax'=>[-20,20],'hppercent'=>[-25,25],'turnmin'=>[-5,5],'turnmax'=>[-5,5],'buffrounds'=>[0,127]];
        $modifiers=['buffatkmod','buffdefmod','buffdmgmod','buffdmgshield'];
        $allowed=[...array_keys($text),...array_keys($ranges),...$modifiers,'drinkid','csrf_token','action_token','showFormTabIndex'];
        if (array_diff(array_keys($post),$allowed)!==[]) { throw new \InvalidArgumentException('Unknown drink field.'); }
        $values=[];
        foreach ($text as $key=>$max) {
            $value=Input::string($post,$key);
            if (!mb_check_encoding($value,'UTF-8') || mb_strlen($value)>$max) { throw new \InvalidArgumentException('Invalid drink text.'); }
            $values[$key]=$value;
        }
        foreach ($ranges as $key=>[$min,$max]) {
            $value=Input::integer($post,$key,0,$min);
            if ($value>$max) { throw new \InvalidArgumentException('Invalid drink value.'); }
            $values[$key]=$value;
        }
        foreach ($modifiers as $key) {
            $value=Input::string($post,$key,'0');
            if ($value==='') $value='0';
            if (!preg_match('/\A-?(?:(?:0|[1-9][0-9]{0,5})(?:\.[0-9]{1,6})?|\.[0-9]{1,6})\z/',$value)) { throw new \InvalidArgumentException('Invalid drink modifier.'); }
            $values[$key]=$value;
        }
        if ($values['hpmin']>$values['hpmax'] || $values['turnmin']>$values['turnmax']) { throw new \InvalidArgumentException('Invalid drink range.'); }
        return $values;
    }
}
