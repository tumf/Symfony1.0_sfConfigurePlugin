<?php

class sfConfigurePlugin
{
    private static $items = array();

    /**
     *  $item = array(
     *    "option" => "--with-dsn=DSN",
     *    "default" => "mysql://root:@localhost/dbname",
     *    "description" => "DSN string"
     *  )
     */
    public static function addItem($name,$item,$group = "etc"){
        self::$items[$group][$name] = $item;
    }
    public static function getItems(){
        return self::$items;
    }
    public static function getItem($name){
        return self::$items[$name];
    }
    public static function getItemDefault($name){
        return self::$items[$name]["default"];
    }
    public static function getItemDefaults($task, $args){
        $project_name = $task->get_property('name','symfony');
        $defaults = array();
        $defaults["AUTHOR"] = "Your Name Here";
        $defaults["PHP_FCGI"] = "/usr/local/bin/php-fcgi";
        $defaults["SYMFONY_DATA_DIR"] = "/usr/local/lib/php/data/symfony";
        $defaults["SYMFONY_LIB_DIR"] = "/usr/local/lib/php/symfony";

        $defaults["DBTYPE"] = "mysql";
        $defaults["DSN_ROOT"] = $defaults["DBTYPE"]."://root:@localhost/";
        $defaults["DBNAME"] = $project_name;
        $defaults["DSN"] = $defaults["DSN_ROOT"] . $defaults["DBNAME"];
        foreach(self::$items as $groups){
            foreach($groups as $name => $item){
                $defaults[$name] = $item['default'];
            }
        }
        
        $user_defaults = self::loadUserDefaults();
        $defaults = self::setIfExisted($user_defaults, $defaults);
        return $defaults;
    }
    protected static function setIfExisted(array $from,array $to){
        foreach($from as $name => $value){
            if(isset($from[$name])){
                $to[self::toToken($name)] = $value;
            }
        }
        return $to;
    }
    /*
     * php-fcgi -> PHP_FCGI
     */
    public static function toToken($str){
        return str_replace("-","_",strtoupper($str));
    }
    protected static function loadUserDefaults($name = "default"){
        $homedir=getenv("HOME");
        if(!strlen($homedir)){
            return array();
        }
        $config_yaml = sprintf("%s/.symfony/configure.yml",$homedir);
        if(is_readable($config_yaml)){
            $config = sfYaml::load($config_yaml);
            return $config[$name];
        }
        return array();
    }
    public static function getHelp(){
        $help = "./configure\n";
        $help .= "[general]\n";
        $help .= sprintf(
                         "     %-25s %s\n",
                         "--help","this help");
        $help .= sprintf(
                         "     %-25s %s\n",
                         "--(add|append|again)","append previous confiugre");

        $help .= sprintf(
                         "     %-25s %s\n",
                         "--status","show configure status");


        foreach(self::$items as $group => $groups){
            $help .= "[".$group."]\n";
            foreach($groups as $item){
                $help .= sprintf(
                                 "     %-25s %s [%s]\n",
                                 $item["option"],
                                 $item["description"],
                                 $item["default"]);
            }
        }
        return $help;
    }
    static function parseDSNopts($args)
    {
        $opts = array();
        foreach ($args as $arg){
            if(preg_match("/^--with-([^=]+)=(.*)/",$arg,$m)){
                $k = self::toToken($m[1]);
                $opts[$k] = $m[2];
            }
        }
        return $opts;
        
    }
    
    static function parseDSN($tokens)
    {
        if (isset($tokens["DSN"])){
            $dsn = parse_url($tokens["DSN"]);
            $tokens["DBNAME"] = substr($dsn["path"],1);
            $tokens["DBTYPE"] = $dsn["scheme"];

            $tokens["DSN_ROOT"]
                = sprintf("%s://",$dsn["scheme"]);
            if (isset($dsn["user"])){
                $tokens["DSN_ROOT"] .= $dsn["user"];
                if (isset($dsn["pass"])){
                    $tokens["DSN_ROOT"] .= ":".$dsn["pass"];
                }
                $tokens["DSN_ROOT"] .= "@";
            }
            if (isset($dsn["host"])){
                $tokens["DSN_ROOT"] .= $dsn["host"];
                if (isset($dsn["port"])){
                    $tokens["DSN_ROOT"] .= ":".$dsn["port"];
                }
                $tokens["DSN_ROOT"] .= "/";
            }
        } else {
            $tokens["DSN"] = $tokens["DSN_ROOT"].$tokens["DBNAME"];
        }
        return $tokens;
    }
    
}
