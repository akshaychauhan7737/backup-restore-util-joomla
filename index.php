<?php
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

// echo "GET::" . json_encode($_GET);
// echo "POST::" . json_encode($_POST);
// header("Content-type: text/xml");
init_backup_utility();

function init_backup_utility()
{
    $GLOBALS['table_prefix'] = isset($_POST['db_prefix']) ? $_POST['db_prefix'] : '';
    if (
        isset($_POST['db_host']) &&
        isset($_POST['db_username']) &&
        isset($_POST['db_password']) &&
        isset($_POST['db_prefix']) &&
        isset($_POST['db_name'])
    ) {
        Helper::init_db_connection($_POST['db_host'], $_POST['db_username'], $_POST['db_password'], $_POST['db_name']);
        Controller::main_controller();
    } else {
        Controller::show_login_screen();
    }

    exit();
}

class Controller
{
    public static function main_controller()
    {
        if (isset($_POST['show_modules_list'])) {
            self::show_modules_list();
        } else
        if (isset($_POST['show_menu_list'])) {
            self::show_menu_list();
        } else
        if (isset($_POST['show_article_list'])) {
            self::show_article_list();
        } else

        if (isset($_POST['generate_menu_backup'])) {
            self::generate_menu_backup();
        } else
        if (isset($_POST['generate_module_backup'])) {
            self::generate_module_backup();
        } else
        if (isset($_POST['generate_article_backup'])) {
            self::generate_article_backup();
        } else

        if (isset($_POST['restore_menu_backup'])) {
            self::restore_menu_backup();
        } else
        if (isset($_POST['restore_module_backup'])) {
            self::restore_module_backup();
        } else
        if (isset($_POST['restore_article_backup'])) {
            self::restore_article_backup();
        } else {
            self::show_home_screen();
        }
    }

    public static function restore_module_backup()
    {
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
        if (isset($_FILES['restore_module_backup_file']['tmp_name'])) {
            header("Content-type: text/html");
            $file_content = file_get_contents($_FILES['restore_module_backup_file']['tmp_name']);
            $data = (array) json_decode($file_content);

            if (isset($data['modules'])) {
                $error = false;
                DB::start_transaction();
                foreach ($data['modules'] as $module_key => $module_data) {
                    $module_data_to_insert = (array) $module_data;
                    if (JoomlaHelper::insert_module_recursively($module_data_to_insert, $data) == false) {
                        echo "<li>" . "Error: " . $module_data_to_insert['title'] . "</li>";
                        $error = true;
                        break;
                    } else {
                        echo "<li>" . "Success: " . $module_data_to_insert['title'] . "</li>";
                    }
                }
                if ($error) {
                    DB::rollback();
                } else {
                    DB::commit();
                }
            }

        } else {
            Helper::show_error('No File Submited');
            return Controller::show_menu_list();
        }
    }
    public static function restore_menu_backup()
    {

        error_reporting(E_ALL);
        ini_set('display_errors', 1);
        if (isset($_FILES['restore_menu_backup_file']['tmp_name'])) {
            header("Content-type: text/html");
            $file_content = file_get_contents($_FILES['restore_menu_backup_file']['tmp_name']);
            $data = (array) json_decode($file_content);

            if (isset($data['menu'])) {
                $error = false;
                DB::start_transaction();
                foreach ($data['menu'] as $menu_key => $menu_data) {
                    $menu_data_to_insert = (array) $menu_data;
                    if (JoomlaHelper::insert_menu_recursively($menu_data_to_insert, $data) == false) {
                        echo "<li>" . "Error: " . $menu_data_to_insert['title'] . "</li>";
                        $error = true;
                        break;
                    } else {
                        echo "<li>" . "Success: " . $menu_data_to_insert['title'] . "-::ALIAS AS:::-" . $menu_data_to_insert['alias'] . "</li>";
                    }
                }
                if ($error) {
                    DB::rollback();
                } else {
                    DB::commit();
                }
            }
        } else {
            Helper::show_error('No File Submited');
            return Controller::show_menu_list();
        }
    }

    public static function generate_module_backup()
    {
        if (isset($_POST['output_type'])) {
            header("Content-type: " . $_POST['output_type']);
        } else {
            header("Content-type: text/text");
        }

        if (!isset($_POST['selected_modules'])) {
            echo Helper::show_error("No Option Choosed");
            Controller::show_home_screen();
            return;
        }

        $module_backup = array();
        foreach ($_POST['selected_modules'] as $module_id) {
            JoomlaHelper::generate_module_backup_recursively($module_id, $module_backup);
        }
        exit(json_encode($module_backup));
    }

    public static function generate_article_backup()
    {

    }

    public static function generate_menu_backup()
    {
        if (isset($_POST['output_type'])) {
            header("Content-type: " . $_POST['output_type']);
        } else {
            header("Content-type: text/text");
        }

        if (!isset($_POST['selected_menu'])) {
            echo Helper::show_error("No Option Choosed");
            Controller::show_home_screen();
            return;
        }
        $selected_menu = $_POST['selected_menu'];
        $module_main_table = $GLOBALS['table_prefix'] . "_menu";
        $menu_types_table = $GLOBALS['table_prefix'] . "_menu_types";

        $get_menu = "SELECT * FROM `" . $module_main_table . "` WHERE id IN(" . implode(',', $selected_menu) . ") ORDER BY id ASC";
        $result = DB::query($GLOBALS['conn'], $get_menu);
        $backup_data_for_xml = array();
        $backup_data_for_xml['menu'] = array();
        $backup_data_for_xml['parent_menu_array'] = array();
        $backup_data_for_xml['menu_type_array'] = array();

        while ($row = DB::get_assoc_row($result)) {
            $menu_type = JoomlaHelper::getMenuTypeWithMenutype($row['menutype']);

            if ($row['template_style_id'] != 0) {
                $template_style = JoomlaHelper::getTemplateWithID($row['template_style_id']);
                if ($template_style) {
                    $backup_data_for_xml['template_array']["parent_" . $row['template_style_id']] = $template_style;
                }
            }
            if ($row['type'] == 'alias') {
                $params = (array) json_decode($row['params']);
                if (!isset($backup_data_for_xml['parent_menu_array']["parent_" . $params['aliasoptions']])) {
                    $parent_alias_menu = JoomlaHelper::getMenuWithID($params['aliasoptions']);
                    $backup_data_for_xml['parent_menu_array']["parent_" . $params['aliasoptions']] = $parent_alias_menu;
                    
                    if(!isset($backup_data_for_xml['menu_type_array']['menu_type_' . $parent_alias_menu['menutype']])){
                        $alias_menu_type = JoomlaHelper::getMenuTypeWithMenutype($parent_alias_menu['menutype']);
                        $backup_data_for_xml['menu_type_array']['menu_type_' . $parent_alias_menu['menutype']] = $alias_menu_type;
                    }
                }
            }

            $current_menu = $row;
            while ($current_menu['parent_id'] != 1) {
                if (!isset($backup_data_for_xml['parent_menu_array']["parent_" . $current_menu['parent_id']])) {
                    $current_menu = JoomlaHelper::getMenuWithID($current_menu['parent_id']);
                    $backup_data_for_xml['parent_menu_array']["parent_" . $current_menu['id']] = $current_menu;
                } else {
                    break;
                }
            }

            $backup_data_for_xml['menu']['menu_' . $row['id']] = $row;
            $backup_data_for_xml['menu_type_array']['menu_type_' . $menu_type['menutype']] = $menu_type;
        }
        echo $menu = json_encode($backup_data_for_xml);
        exit();
    }

    public static function show_home_screen()
    {
        echo '<li><form method="POST" action=""><input type="submit" name="show_menu_list" value="show_menu_list">' . Helper::getcommonFields() . '</form></li>';
        echo '<li><form method="POST" action=""><input type="submit" name="show_modules_list" value="show_modules_list">' . Helper::getcommonFields() . '</form></li>';
        echo '<li><form method="POST" action=""><input type="submit" name="show_article_list" value="show_article_list">' . Helper::getcommonFields() . '</form></li>';
        echo '<li><h3>Menu Restore</h3><form method="POST" action="" enctype="multipart/form-data"><input type="file" name="restore_menu_backup_file" required="required"><input type="submit" name="restore_menu_backup" value="Restore Menu">' . Helper::getcommonFields() . '</form></li>';
        echo '<li><h3>Module Restore</h3><form method="POST" action="" enctype="multipart/form-data"><input type="file" name="restore_module_backup_file" required="required"><input type="submit" name="restore_module_backup" value="Restore Module">' . Helper::getcommonFields() . '</form></li>';
        echo '<li><h3>Article Restore</h3><form method="POST" action="" enctype="multipart/form-data"><input type="file" name="restore_article_backup_file" required="required"><input type="submit" name="restore_article_backup" value="Restore Article">' . Helper::getcommonFields() . '</form></li>';
    }

    public static function show_login_screen()
    {
        echo '<form action="#" method="post">
            <input type="text" name="db_host" placeholder="db_host">
            <input type="text" name="db_username" placeholder="db_username">
            <input type="text" name="db_name" placeholder="db_name">
            <input type="text" name="db_prefix" placeholder="db_prefix">
            <input type="password" name="db_password" placeholder="db_password">
            <input type="submit" name="form_submit" value="Login">
        </form>';
    }

    public static function show_article_list()
    {
        $article_main_table = $GLOBALS['table_prefix'] . "_modules";

    }
    public static function show_modules_list()
    {
        echo '<html><title>Adminer</title><body>';
        $module_main_table = $GLOBALS['table_prefix'] . "_modules";
        $query = "SELECT * FROM `$module_main_table` ORDER BY id DESC";
        $result = DB::query($GLOBALS['conn'], $query);
        print_r(mysqli_error($GLOBALS['conn']));

        echo '<h1>Module List</h1>';
        echo '<br><form method="POST" action=""><table border="1" style="border-width: thick;width:100%"">';
        echo '<select name="output_type"><option value="text/json">Json Format to read</option><option value="text/text">text Format to copy</option></select>';
        echo '<input type="submit" name="generate_module_backup" value="Generate Module Backup">';
        $first = false;
        while ($get_assoc_row = DB::get_assoc_row($result)) {
            if (!$first) {
                $first = true;
                $get_colums_name = array_keys($get_assoc_row);
                echo "<tr>";
                echo '<th>Select</th>';
                foreach ($get_colums_name as $column) {
                    if ($column == 'content' || $column == 'params') {
                        continue;
                    }

                    echo '<th>' . $column . '</th>';
                }
                echo "</tr>";
            }
            echo "<tr>";
            echo '<td><input type="checkbox" value="' . $get_assoc_row['id'] . '" name="selected_modules[]"></td>';
            foreach ($get_assoc_row as $column => $value) {
                if ($column == 'content' || $column == 'params') {
                    continue;
                }

                echo '<td>' . $value . '</td>';
            }
            echo "</tr>";
        }
        echo "</table>";
        echo Helper::getcommonFields();
        echo "</form>";
        echo '</body></html>';
    }
    public static function show_menu_list()
    {
        echo '<html><title>Adminer</title><body>';
        $module_main_table = $GLOBALS['table_prefix'] . "_menu";
        $query = "SELECT * FROM `$module_main_table` WHERE published = 1 ORDER BY id DESC";
        $result = DB::query($GLOBALS['conn'], $query);
        print_r(mysqli_error($GLOBALS['conn']));

        echo '<h1>Menu List</h1>';
        echo '<br><form method="POST" action=""><table border="1" style="border-width: thick;width:100%">';
        echo '<select name="output_type"><option value="text/json">Json Format to read</option><option value="text/text">text Format to copy</option></select>';
        echo '<input type="submit" name="generate_menu_backup" value="Generate Backup">';
        $first = false;
        while ($get_assoc_row = DB::get_assoc_row($result)) {
            if (!$first) {
                $first = true;
                $get_colums_name = array_keys($get_assoc_row);
                echo "<tr>";
                echo '<th>Select</th>';
                foreach ($get_colums_name as $column) {
                    if ($column == 'content' || $column == 'params') {
                        continue;
                    }

                    echo '<th>' . $column . '</th>';
                }
                echo "</tr>";
            }
            echo "<tr>";
            echo '<td><input type="checkbox" value="' . $get_assoc_row['id'] . '" name="selected_menu[]"></td>';
            foreach ($get_assoc_row as $column => $value) {
                if ($column == 'content' || $column == 'params') {
                    continue;
                }

                echo '<td>' . $value . '</td>';
            }
            echo "</tr>";
        }
        echo "</table>";
        echo Helper::getcommonFields();
        echo "</form>";
        echo '</body></html>';
    }
}
class DB
{

    public static function connect($db_host, $db_username, $db_password, $db_name)
    {
        return mysqli_connect($db_host, $db_username, $db_password, $db_name);
    }
    public static function query($conn, $query)
    {
        return mysqli_query($conn, $query);
    }
    public static function get_assoc_row($result)
    {
        return mysqli_fetch_assoc($result);
    }
    public static function num_rows($result)
    {
        return mysqli_num_rows($result);
    }
    public static function start_transaction()
    {
        self::query($GLOBALS['conn'], "SET AUTOCOMMIT=0");
        return self::query($GLOBALS['conn'], "START TRANSACTION");
    }
    public static function commit()
    {
        $response = self::query($GLOBALS['conn'], "COMMIT");
        self::query($GLOBALS['conn'], "SET AUTOCOMMIT=1");
        return $response;
    }
    public static function rollback()
    {
        $response = self::query($GLOBALS['conn'], "ROLLBACK");
        self::query($GLOBALS['conn'], "SET AUTOCOMMIT=1");
        return $response;
    }
    public static function get_insert_id()
    {
        return mysqli_insert_id($GLOBALS['conn']);
    }
}

class Helper
{
    public static function array2xml($array, $xml = false)
    {

        if ($xml === false) {
            $xml = new SimpleXMLElement('<result/>');
        }

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                self::array2xml($value, $xml->addChild($key));
            } else {
                $xml->addChild($key, $value);
            }
        }

        return $xml->asXML();
    }
    public static function init_db_connection($db_host, $db_username, $db_password, $db_name)
    {
        $GLOBALS['conn'] = DB::connect($db_host, $db_username, $db_password, $db_name);
        if (mysqli_error($GLOBALS['conn'])) {
            echo json_encode($error);
            show_login_screen();
            return false;
        } else {
            return $GLOBALS['conn'];
        }

    }
    public static function getcommonFields()
    {
        return '
    <input type="hidden" name="db_host" value="' . $_POST['db_host'] . '">
    <input type="hidden" name="db_username" value="' . $_POST['db_username'] . '">
    <input type="hidden" name="db_name" value="' . $_POST['db_name'] . '">
    <input type="hidden" name="db_prefix" value="' . $_POST['db_prefix'] . '">
    <input type="hidden" name="db_password" value="' . $_POST['db_password'] . '">';
    }
    public static function show_error($error)
    {
        return '<div class="error" style="color:red;">' . $error . '</div>';
    }
}

class JoomlaHelper
{
    const FIND_WITH_ALIAS = 1000;
    const FIND_WITH_MENU_TYPE = 1001;

    public static function getTemplateWithID($id)
    {
        $template_styles_table = $GLOBALS['table_prefix'] . '_template_styles';
        $query = "SELECT * FROM `" . $template_styles_table . "` WHERE id = " . $id;
        $result = DB::query($GLOBALS['conn'], $query);
        return DB::get_assoc_row($result);
    }

    public static function getTemplateWithName($template)
    {
        $template_styles_table = $GLOBALS['table_prefix'] . '_template_styles';
        $query = "SELECT * FROM `" . $template_styles_table . "` WHERE template = '" . $template . "'";
        $result = DB::query($GLOBALS['conn'], $query);
        return DB::get_assoc_row($result);
    }

    public static function getMenuWithID($id)
    {
        $module_main_table = $GLOBALS['table_prefix'] . '_menu';
        $query = "SELECT * FROM `" . $module_main_table . "` WHERE id = " . $id;
        $result = DB::query($GLOBALS['conn'], $query);
        return DB::get_assoc_row($result);
    }

    public static function getMenuTypeWithMenutype($menutype)
    {
        $menu_types_table = $GLOBALS['table_prefix'] . "_menu_types";
        $query = "SELECT * FROM `" . $menu_types_table . "` where menutype = '" . $menutype . "'";
        $result = DB::query($GLOBALS['conn'], $query);
        return DB::get_assoc_row($result);
    }

    public static function find_menu_type($find_with, $data_find_data)
    {
        $module_main_table = $GLOBALS['table_prefix'] . "_menu";
        $menu_types_table = $GLOBALS['table_prefix'] . "_menu_types";
        $data_find_data = (array) $data_find_data;

        $query = "SELECT * FROM `" . $menu_types_table . "`";
        switch ($find_with) {
            case self::FIND_WITH_MENU_TYPE:
                $query .= " WHERE menutype='" . $data_find_data['menutype'] . "'";
                break;
            default:return null;
        }
        $result = DB::query($GLOBALS['conn'], $query);
        $row = DB::get_assoc_row($result);
        if (!$row) {
            echo '<br>---------------------<br>';
            echo "please create menutype as:" . $data_find_data['menutype'];
            return false;
        }
        return true;
    }

    public static function find_menu($find_with, $data_find_data)
    {
        $module_main_table = $GLOBALS['table_prefix'] . "_menu";
        $menu_types_table = $GLOBALS['table_prefix'] . "_menu_types";

        $query = "SELECT * FROM `" . $module_main_table . "`";
        switch ($find_with) {
            case self::FIND_WITH_ALIAS:
                $query .= " WHERE alias='" . $data_find_data . "'";
                break;
            default:return null;
        }
        $result = DB::query($GLOBALS['conn'], $query);
        return DB::get_assoc_row($result);
    }

    // public static function insert_menu_type($menu_data){
    //     $menu_types_table = $GLOBALS['table_prefix'] . "_menu_types";
    //     $insert_menu_query = "INSERT INTO `".$menu_types_table."` SET ";

    //     foreach($menu_data as $column => $data){
    //         if(!is_numeric($data)){
    //             $insert_menu_query .=  $column."='".$data."',";
    //         }else{
    //             $insert_menu_query .=  $column."=".$data.",";
    //         }
    //     }
    //     $insert_menu_query = trim($insert_menu_query,',');

    //     if($insert_query_result = DB::query($GLOBALS['conn'],$insert_menu_query)){
    //         return DB::get_insert_id();
    //     }else {
    //         echo $insert_menu_query;
    //         print_r(mysqli_error($GLOBALS['conn']));
    //         echo '<br>---------------------<br>';
    //         return false;
    //     }
    // }

    public static function insert_menu($menu_data)
    {
        $module_main_table = $GLOBALS['table_prefix'] . "_menu";
        $menu_types_table = $GLOBALS['table_prefix'] . "_menu_types";
        $insert_menu_query = "INSERT INTO `" . $module_main_table . "` SET ";

        // unset($menu_data['checked_out_time']);
        foreach ($menu_data as $column => $data) {
            if ($column == 'checked_out_time') {
                $insert_menu_query .= $column . "=NOW(),";
            } else
            if (!is_numeric($data)) {
                $insert_menu_query .= $column . "='" . $data . "',";
            } else {
                $insert_menu_query .= $column . "=" . $data . ",";
            }
        }
        $insert_menu_query = trim($insert_menu_query, ',');

        if ($insert_query_result = DB::query($GLOBALS['conn'], $insert_menu_query)) {
            return DB::get_insert_id();
        } else {
            echo $insert_menu_query;
            print_r(mysqli_error($GLOBALS['conn']));
            echo '<br>---------------------<br>';
            return false;
        }
    }
    public static function insert_asset($menu_data){
        $module_main_table = $GLOBALS['table_prefix'] . "_assets";
        $insert_menu_query = "INSERT INTO `" . $module_main_table . "` SET ";

        // unset($menu_data['checked_out_time']);
        foreach ($menu_data as $column => $data) {
            if ($column == 'checked_out_time') {
                $insert_menu_query .= $column . "=NOW(),";
            } else
            if (!is_numeric($data)) {
                $insert_menu_query .= $column . "='" . $data . "',";
            } else {
                $insert_menu_query .= $column . "=" . $data . ",";
            }
        }
        $insert_menu_query = trim($insert_menu_query, ',');

        if ($insert_query_result = DB::query($GLOBALS['conn'], $insert_menu_query)) {
            return DB::get_insert_id();
        } else {
            echo $insert_menu_query;
            print_r(mysqli_error($GLOBALS['conn']));
            echo '<br>---------------------<br>';
            return false;
        }
    }
    public static function insert_module($menu_data){
        $module_main_table = $GLOBALS['table_prefix'] . "_modules";
        $insert_menu_query = "INSERT INTO `" . $module_main_table . "` SET ";

        // unset($menu_data['checked_out_time']);
        foreach ($menu_data as $column => $data) {
            if ($column == 'checked_out_time' || $column == 'publish_down' || $column == 'publish_down') {
                $insert_menu_query .= $column . "=NOW(),";
            } else
            if (!is_numeric($data)) {
                $insert_menu_query .= $column . "='" . $data . "',";
            } else {
                $insert_menu_query .= $column . "=" . $data . ",";
            }
        }
        $insert_menu_query = trim($insert_menu_query, ',');

        if ($insert_query_result = DB::query($GLOBALS['conn'], $insert_menu_query)) {
            return DB::get_insert_id();
        } else {
            echo $insert_menu_query;
            print_r(mysqli_error($GLOBALS['conn']));
            echo '<br>---------------------<br>';
            return false;
        }
    }

    public static function insert_menu_recursively($menu_data, $data)
    {
        $menu_data = (array) $menu_data;
        $menu = JoomlaHelper::find_menu(JoomlaHelper::FIND_WITH_ALIAS, $menu_data['alias']);
        if (!$menu) {

            if ($menu_data['template_style_id'] != 0) {
                $template_style = $data['template_array']['parent_' . $menu_data['template_style_id']];
                $template = self::getTemplateWithName($template_style['template']);
                if ($template) {
                    $menu_data['template_style_id'] = $template['id'];
                } else {
                    echo "<br>-----------------<br>";
                    echo "<div>ERR: Please install template : " . $template_style['template'];
                    return false;
                }
            }

            if ($menu_data['type'] == 'alias') {
                $params = json_decode($menu_data['params']);
                if (isset($data['parent_menu_array']->{'parent_' . $params->aliasoptions})) {
                    $menu_to_insert = $data['parent_menu_array']->{'parent_' . $params->aliasoptions};
                } else if (isset($data['menu']->{'menu_' . $params->aliasoptions})) {
                    $menu_to_insert = $data['menu']->{'menu_' . $params->aliasoptions};
                } else {
                    echo '<br>-------------<br>';
                    echo "ERR: Menu not included: " . $params->aliasoptions;
                    return false;
                }

                if ($alias_parent_id = self::insert_menu_recursively($menu_to_insert, $data)) {
                    $params->aliasoptions = $alias_parent_id;
                    $menu_data['params'] = json_encode($params);
                } else {
                    return false;
                }
            }

            $parent_id_search = $menu_data['parent_id'];
            if ($parent_id_search == "1") {
                $parent_id = "1";
            } else
            if (isset($data['parent_menu_array']->{'parent_' . $parent_id_search})) {
                $parent_id = self::insert_menu_recursively($data['parent_menu_array']->{'parent_' . $parent_id_search}, $data);
            } else if (isset($data['menu']->{'menu_' . $parent_id_search})) {
                $parent_id = self::insert_menu_recursively($data['menu']->{'menu_' . $parent_id_search}, $data);
            } else {
                echo '<br>-------------<br>';
                echo "ERR: Menu not included: " . $parent_id_search;
                return false;
            }

            if ($parent_id == false) {
                return false;
            }

            $menu_data['parent_id'] = $parent_id;
            unset($menu_data['id']);
            
            if (!self::find_menu_type(JoomlaHelper::FIND_WITH_MENU_TYPE, $data['menu_type_array']->{'menu_type_' . $menu_data['menutype']})) {
                echo '<br>-------------<br>';
                echo "ERR: First Create a menu-type: " . $menu_data['menutype'];
                return false;
            }

            if ($insert_id = self::insert_menu($menu_data)) {
                return $insert_id;
            } else {
                return false;
            }
        } else {
            return $menu['id'];
        }
    }
    public static function insert_asset_recursively($asset_id,$data){
        // $data['assets'] = (array)$data['assets'];
        if($asset_id == "1"){
            return "1";
        }
        if(isset($data['assets']->{$asset_id})){
            $current_asset = (array)$data['assets']->{$asset_id};
            $asset_search = self::findAssetByTitle($current_asset['title']);
            if($asset_search){
                return $asset_search['id'];
            }else{
                $parent_id = self::insert_asset_recursively($current_asset['parent_id'],$data);
                $current_asset['parent_id'] = $parent_id;
                unset($current_asset['id']);
                if($id = self::insert_asset($current_asset)){
                    return $id;
                }else return false;
            }
        }else {
            echo "ERR: NOT INCLUDED asset_id :".$data['assets'][$asset_id];
            return false;
        }
    }
    public static function insert_module_menu($module_id,$module_array,$data){
        foreach($module_array as $menu_id){
            $menu = (array)$data['modules_menu_data']->{$menu_id};
            $menu_data = self::find_menu(self::FIND_WITH_ALIAS,$menu['alias']);
            if($menu_data){
                if(self::insert_module_menu_data($module_id,$menu_data) == false){
                    echo "<br>------------<br>";
                    echo "ERR: _modules_menu : cannot be inserted data to table : ";
                    return false;
                }
            }else{
                echo "<br>------------<br>";
                echo "ERR: Menu not moved with alias: ".$menu['alias'];
                return false;
            }
            
        }
    }

    public static function insert_module_menu_data($module_id,$get_menu_by_id){
        $table_name = $GLOBALS['table_prefix'].'_modules_menu';
        $query = "INSERT INTO `".$table_name."` set moduleid = ".$module_id.", menuid=".$get_menu_by_id['id'];
        $result = DB::query($GLOBALS['conn'], $query);
        return mysqli_insert_id($GLOBALS['conn']);
    }

    public static function insert_module_recursively($module_data, $data){
        $module_data = (array) $module_data;
        $module = self::getModuleByTitle($module_data['title']);
        if(!$module){
            $asset_id = self::insert_asset_recursively($module_data['asset_id'],$data);
            if($asset_id ==false){
                return false;
            }
            $module_data['asset_id'] = $asset_id;
            $old_module_id = $module_data['id'];
            unset($module_data['id']);
            $module_id =  self::insert_module($module_data);
            if(!$module_id){
                return false;
            }
            if(self::insert_module_menu($module_id,$data['module_menu']->{$old_module_id},$data)){
                return $module_id;
            }else return false;
        }else{
            return $module['id'];
        }
    }

    public static function findAssetByTitle($title){
        $_assets_table = $GLOBALS['table_prefix'] . "_assets";
        $query = "SELECT * FROM `{$_assets_table}` WHERE `title` = '" . $title."'";
        $result = DB::query($GLOBALS['conn'], $query);
        return DB::get_assoc_row($result);
    }
    
    public static function findAssetByName($name){
        $_assets_table = $GLOBALS['table_prefix'] . "_assets";
        $query = "SELECT * FROM `{$_assets_table}` WHERE `name` = " . $name;
        $result = DB::query($GLOBALS['conn'], $query);
        return DB::get_assoc_row($result);
    }

    public static function getModuleByID($module_id)
    {
        $module_main_table = $GLOBALS['table_prefix'] . "_modules";
        $modules_menu_table = $GLOBALS['table_prefix'] . "_modules_menu";
        $query = "SELECT * FROM `{$module_main_table}` WHERE id = " . $module_id;
        $result = DB::query($GLOBALS['conn'], $query);
        return DB::get_assoc_row($result);
    }

    public static function getModuleByTitle($module_id)
    {
        $module_main_table = $GLOBALS['table_prefix'] . "_modules";
        $modules_menu_table = $GLOBALS['table_prefix'] . "_modules_menu";
        $query = "SELECT * FROM `{$module_main_table}` WHERE title = '" . $module_id."'";
        $result = DB::query($GLOBALS['conn'], $query);
        return DB::get_assoc_row($result);
    }

    public static function getAssetByID($asset_id)
    {
        $_assets_table = $GLOBALS['table_prefix'] . "_assets";
        $query = "SELECT * FROM `{$_assets_table}` WHERE id = " . $asset_id;
        $result = DB::query($GLOBALS['conn'], $query);
        return DB::get_assoc_row($result);
    }

    public static function getModuleMenuList($moduleid, &$module_array)
    {
        $module_main_table = $GLOBALS['table_prefix'] . "_modules";
        $modules_menu_table = $GLOBALS['table_prefix'] . "_modules_menu";
        $query = "SELECT * FROM `{$modules_menu_table}` WHERE moduleid = " . $moduleid;
        $result = DB::query($GLOBALS['conn'], $query);
        while ($row = DB::get_assoc_row($result)) {
            $module_array[] = $row['menuid'];
        }
    }

    public static function generate_module_backup_recursively($module_id, &$module_backup)
    {
        if (!isset($module_backup['modules'])) {
            $module_backup['modules'] = array();
        }
        if (!isset($module_backup['assets'])) {
            $module_backup['assets'] = array();
        }
        if (!isset($module_backup['module_menu'])) {
            $module_backup['module_menu'] = array();
        }
        if (!isset($module_backup['modules_menu_data'])) {
            $module_backup['modules_menu_data'] = array();
        }

        if (empty($module_id)) {
            return false;
        }

        $current_module = self::getModuleByID($module_id);
        $module_backup['modules'][$current_module['id']] = $current_module;

        $current_asset = self::getAssetByID($current_module['asset_id']);
        $module_backup['assets'][$current_asset['id']] = $current_asset;

        $asset = $current_asset;
        while ($asset['id'] != '1') {
            $module_backup['assets'][$asset['id']] = $asset = self::getAssetByID($asset['parent_id']);
        }
        $module_backup['module_menu'][$current_module['id']] = array();
        self::getModuleMenuList($current_module['id'], $module_backup['module_menu'][$current_module['id']]);
        foreach ($module_backup['module_menu'][$current_module['id']] as $modules_menu_row) {
            $module_backup['modules_menu_data'][$modules_menu_row] = self::getMenuWithID($modules_menu_row);
        }
    }
}
