<?php
  /**
   * ~/.symfony/configure.yml
   * --------------------------------------------------
   * default:
   *   php-fcgi: /usr/local/bin/php-fcgi
   *   openurl: open
   *   lighttpd: lighttpd
   *   #  server-port:
   *   server-bind: localhost
   *   dsn-root: mysql://root:root@localhost:8889/
   *   symfony-lib-dir: /Users/tumf/lib/php/symfony/lib
   *   symfony-data-dir: /Users/tumf/lib/php/symfony/data
   */
pake_desc('configure this project');
pake_task('configure', 'project_exists');

pake_desc('setup configure');
pake_task('init-configure', 'project_exists');

pake_desc('generate bootstrap');
pake_task('init-bootstrap', 'project_exists');

pake_desc('run bootstrap');
pake_task('bootstrap', 'project_exists');


function __configure_read_php_argv(){
    global $argv;
    if (!is_array($argv)){
        if (!@is_array($_SERVER['argv'])){
            if (!@is_array($GLOBALS['HTTP_SERVER_VARS']['argv'])){
                pake_echo("ERROR: Could not read cmd args (register_argc_argv=Off?).");
                exit;
            }
            return $GLOBALS['HTTP_SERVER_VARS']['argv'];
        }
        return $_SERVER['argv'];
    }
    return $argv;
}
/**
 *
 * symfony configure --with-dsn=mysql://root:@localhost/dbname
 *
 */
function run_configure($task,$args){
    $argv = __configure_read_php_argv();
    $args = array_slice($argv,2);
    
    $begin_token = "##";
    $end_token = "##";
    $ext = ".in";
    $tokens = __configure_get_default($task, $args);
    if(count($args) == 1){
        if($args[0] == "--help"){
            __configure__help();
            return;
        }
        if($args[0] == "--status"){
            __configure__status();
            return;
        }
        if($args[0] == "--status-elisp"){
            __configure__status_elisp();
            return;
        }
    }
    foreach($args as $arg){
        if($arg == "--append" || $arg == "--again"|| $arg == "--add" ){
            if(file_exists("config.status") 
               && $status = file_get_contents("config.status")){
                $tokens = unserialize($status);
            }
        }
    }
    
    $tokens = array_merge($tokens,
                          sfConfigurePlugin::parseDSNopts($args));
    $tokens = sfConfigurePlugin::parseDSN($tokens);

    $templates = pakeFinder::type('file')
        ->ignore_version_control()
        ->name("*.in");
    
    $replaced = false;
    foreach($templates->in(".") as $file){
        $content = file_get_contents($file);
        foreach ($tokens as $key => $value){
            $content = str_replace($begin_token.$key.$end_token, $value, $content, $count);
            if ($count) $replaced = true;
        }
        $target = substr($file,0,-3);
        pake_echo_action('tokens', $target);
        file_put_contents($target, $content);
    }
    // config.status
    if($replaced){
        file_put_contents("config.status",serialize($tokens));
        pake_echo_action('+file', "config.status");
    }

}
function run_init_bootstrap($task,$args){
    $bootstrap = '#!/usr/bin/env bash
mkdir -p cache log web/uploads/assets web/cache data/fixtures
if [ ! -f config/config.php ]; then
    if [ -f ~/.symfony/configure.yml ]; then
        symfony_lib_dir=`grep symfony-lib-dir ~/.symfony/configure.yml|cut -d \':\' -f 2`
        symfony_data_dir=`grep symfony-data-dir ~/.symfony/configure.yml|cut -d \':\' -f 2`
    fi
    if [ -z ${symfony_data_dir} ]; then
        symfony_data_dir=`pear config-get data_dir`/symfony
    fi
    if [ -z ${symfony_lib_dir} ]; then
        symfony_lib_dir=`pear config-get php_dir`/symfony
    fi
    
    cat >config/config.php <<EOF
<?php
// symfony directories
\$sf_symfony_lib_dir  = \'${symfony_lib_dir# }\';
\$sf_symfony_data_dir = \'${symfony_data_dir# }\';
EOF
fi

if [ ! -f config/properties.ini ]; then
    cp config/properties.ini.in config/properties.ini
fi

symfony fix-perms
chmod 777 web/cache
';
    file_put_contents("bootstrap",$bootstrap);

    pake_echo_action("+file","boostrap");
    pake_sh("chmod +x bootstrap");
}



function run_bootstrap($task,$args){
    pake_sh("./bootstrap");
}

// symfony configure-setup
function run_init_configure($task,$args){
    __configure_generate_script();
    __configure_setup_file("config/config.php");
    __configure_setup_file("config/propel.ini");
    __configure_setup_file("config/properties.ini");
    __configure_setup_file("config/databases.yml");

    $prop = file_get_contents("config/properties.ini.in");
    if(!preg_match("/  author=/",$prop)){
        $prop
            = preg_replace("/^\[symfony\]$/m"
                           ,"\${0}\n  author=##AUTHOR##",$prop);
        file_put_contents("config/properties.ini.in",$prop);
        pake_echo_action("+file","config/properties.ini.in");
    }
}
function __configure__help(){
    pake_echo(sfConfigurePlugin::getHelp());
}

function __configure__status(){
    if(is_readable("config.status")){
        $array = unserialize(file_get_contents("config.status"));
        echo sfYaml::dump($array);
    }
}
function __configure__status_elisp(){
    if(is_readable("config.status")){
        $array = unserialize(file_get_contents("config.status"));
        $lisp = "(setq symfony-configure:status-alist '(";
        foreach($array as $k => $v){
            $lisp .= sprintf("(\"%s\" . \"%s\")\n",
                             strtr(strtolower($k),"_","-"),$v);
        }
        $lisp .= "))";
        echo $lisp;
    }
}

function __configure_generate_script(){
    $configure = "#!/usr/bin/env bash
if [ -f config/config.php ]; then
    ./symfony configure $*
else
    symfony -f plugins/sfConfigure/data/tasks/sfConfigureTask.php configure $*
fi
";
    file_put_contents("configure",$configure);

    pake_echo_action("+file","configure");
    pake_sh("chmod +x configure");
}

function __configure_setup_file($file){
    $content = file_get_contents($file);
    $pattern = array(
          "/(sf_symfony_lib_dir\s*=\s*\')(.*)(\';)/",
          "/(sf_symfony_data_dir\s*=\s*\')(.*)(\';)/",
          "/(propel\.database\s*=\s*)([^\s]*)/",
          "/(propel\.database\.createUrl\s*=\s*)([^\s]*)/",
          "/(propel\.database\.url\s*=\s*)([^\s]*)/",
          "/(propel\.output\.dir\s*=\s*)([^\s]*)/",
          "/(dsn:\s+)([^\s]*)/",
          //"/^[\s]{2}propel:[\s]*$/m"
          );
    $replace = array(
                      "$1##SYMFONY_LIB_DIR##$3",
                      "$1##SYMFONY_DATA_DIR##$3",
                      "$1##DBTYPE##",
                      "$1##DSN_ROOT##",
                      "$1##DSN##",
                      "$1##PWD##",
                      "$1##DSN##",
                      //"  ##DBNAME##:",
                      );
    $content = preg_replace($pattern,$replace,$content);

    $target = $file . ".in";
    pake_echo_action('tokens', $target);
    file_put_contents($target, $content);
}


function __configure_get_default($task,$args){
    global $sf_symfony_lib_dir;
    global $sf_symfony_data_dir;

    $project_name = $task->get_property('name','symfony');
    $tokens = sfConfigurePlugin::getItemDefaults($task, $args);

    sfConfigurePlugin::addItem
        ("AUTHOR",array(
                     "option"=>"--with-author=AUTHOR" , 
                     "default" => $tokens["AUTHOR"],
                     "description" => "Your Name Here"),"symfony");

    sfConfigurePlugin::addItem
        ("DSN",array(
                     "option"=>"--with-dsn=DSN" , 
                     "default" => $tokens["DSN"],
                     "description" => "Data Source Name"),"database");
    sfConfigurePlugin::addItem
        ("DBNAME",array(
                     "option"=>"--with-dbname=DBNAME" , 
                     "default" => $tokens["DBNAME"],
                     "description" => "Database Name"),"database");
    sfConfigurePlugin::addItem
        ("DBTYPE",array(
                     "option"=>"--with-dbtype=DBTYPE" , 
                     "default" => $tokens["DBTYPE"],
                     "description" => "Database Type"),"database");


    sfConfigurePlugin::addItem
        ("DSN_ROOT",array(
                     "option"=>"--with-dsn-root=DSN_ROOT" , 
                     "default" => $tokens["DSN_ROOT"],
                     "description" => "Data Source Name Root"),"database");

    if(isset($sf_symfony_lib_dir)){
        $tokens["SYMFONY_LIB_DIR"] = $sf_symfony_lib_dir;
    }
    
    if(isset($sf_symfony_data_dir)){
        $tokens["SYMFONY_DATA_DIR"] = $sf_symfony_data_dir;
    }

    sfConfigurePlugin::addItem
        ("SYMFONY_LIB_DIR",array(
                                 "option"=>"--with-symfony-lib-dir=DIR" , 
                                 "default" => $tokens["SYMFONY_LIB_DIR"],
                                 "description" => "symfony lib dir"),"symfony");

    sfConfigurePlugin::addItem
        ("SYMFONY_DATA_DIR",array(
                     "option"=>"--with-symfony-data-dir=DIR" , 
                     "default" => $tokens["SYMFONY_DATA_DIR"],
                     "description" => "symfony data dir"),"symfony");

    $tokens["PWD"] = getcwd();

    return $tokens;
}