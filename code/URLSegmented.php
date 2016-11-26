<?php
/**
 * URLSegmented
 * 
 * Takes care of adding an unique url segment to dataobjects
 * 
 * attach either via 
 * static $extensions = array("URLSegmented"); 
 * from within a DataObject or via
 * Object::add_extension("MyDataObject", "URLSegmented");
 * from your _config.php
 *
 * If you'd like to make use of the DataList::get()->byURL($URLSegment) helper, add this line to your _config
 * Object::add_extension("DataList", "URLSegmented_DataListExtension");
 *
 * PHP version 5.3+
 *	
 * @package    catalog
 * @author     Tim Klein<tim[at]dodat.co.nz>
 * @copyright  2013 Dodat Ltd.
 */
class URLSegmented extends DataExtension
{

    private $Scope;
    private $TitleField;


    public function __construct($scope = null, $titleField = "Title")
    {
        $this->Scope = $scope;
        $this->TitleField = $titleField;
        parent::__construct();
    }

    public static function get_extra_config($class, $extension, $args)
    {
        return array(
            "db" => array(
                "URLSegment" => "Varchar(255)"
            ),
            "indexes" => array(
                "URLSegment" => true
            ),
            "field_labels" => array(
                "URLSegment" => "{$class} URL"
            )
        );
    }


    public function onBeforeWrite()
    {
        if (!$this->owner->URLSegment) {
            $this->owner->URLSegment = $this->generateURLSegment();
        }
    }

    /**
     * just a simple method that sets a default url segment title
     */
    public function generateURLSegment()
    {
        if (method_exists($this->owner, "generateURLSegment")) {
            return $this->owner->generateURLSegment();
        }
        $titleField = $this->TitleField;
        if (!$title = $this->owner->$titleField) {
            $title = $this->owner->i18n_singular_name()."-".$this->owner->ID;
        }
        return $title;
    }

    /**
     * custom setter for urlsegment
     * runs param through urlsegment filter to sanitize any unwanted characters
     * calls existsInScope for uniqueness check, otherwise appends random number until unique
     */
    public function setURLSegment($value)
    {
        $urlSegment = URLSegmentFilter::create()->filter($value);

        while ($this->existsInScope($urlSegment)) {
            $urlSegment = $urlSegment.rand(1, 9);
        }
        $this->owner->setField("URLSegment", $urlSegment);
    }

    /**
     * checks wether the provided param urlSegment exists in the given scope
     * returns bool
     */
    public function existsInScope($urlSegment)
    {
        $class = get_class($this->owner);

        $check = $class::get();

        //base query
        $check = $check->where("URLSegment='{$urlSegment}'");

        //check within scope
        $check = $this->addScopeCheck($check);

        //avoid returning itself
        if ($this->owner->ID) {
            $check = $check->where("ID !='{$this->owner->ID}'");
        }

        return (bool)$check->Count();
    }

    public function addScopeCheck(DataList $list)
    {
        $scopeField = $this->Scope;
        if ($scopeField && ($scopeValue = $this->owner->$scopeField)) {
            //$list = clone $list;
            return $list->where("{$scopeField}='{$scopeValue}'");
        }
        return $list;
    }
}

/** 
* While this is somewhat ugly
* it is the only way of currently (26/09/12) adding custom DataList getter-methods
* This way you can do a DO::get()->byURL($URL)
* attach via 
* Object::add_extension("DataList", "URLSegmented_DataListExtension");
**/
class URLSegmented_DataListExtension extends Extension
{

    public function byURL($url)
    {
        $url_sql = Convert::raw2sql($url);
        $baseClass = ClassInfo::baseDataClass($this->owner->dataClass);

        $sgl = $baseClass::create();
        $required_extension = "URLSegmented";

        if (!$extension_instance = $sgl->getExtensionInstance($required_extension)) {
            trigger_error("{$baseClass} doesnt have the required {$required_extension} extension");
        }
        $list = clone $this->owner;
        $where = "\"{$baseClass}\".\"URLSegment\" = '{$url_sql}'";
        return $list->where($where)->First();
    }
}
