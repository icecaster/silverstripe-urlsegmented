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
class URLSegmented extends DataExtension {

	private $Scope;
	private $TitleField;


	function __construct($scope = null, $titleField = "Title") {
		$this->Scope = $scope;
		$this->TitleField = $titleField;
		parent::__construct();
	}

	public static function get_extra_config($class, $extension, $args) {
		return array(
			"db" => array(
				"URLSegment" => "Varchar(255)"
			),
			"indexes" => array(
				"URLSegment" => true
			),
			"field_labels" => array(
				"URLSegment" => "URL Segment"
			)
		);
	}


	function onBeforeWrite() {
		if(!$this->owner->URLSegment) {
			$this->setURLSegment($this->generateURLSegment());
		}
	}

	/**
	 * just a simple method that sets a default url segment title
	 */
	function generateURLSegment() {
		if(method_exists($this->owner, "generateURLSegment")) {
			return $this->owner->generateURLSegment();
		}
		$titleField = $this->TitleField;
		if(!$title = $this->owner->$titleField) {
			$title = $this->owner->i18n_singular_name()."-".$this->owner->ID;
		}
		return $title;
	}

	/**
	 * custom setter for urlsegment
	 * runs param through urlsegment filter to sanitize any unwanted characters
	 * calls existsInScope for uniqueness check, otherwise appends random number until unique
	 */
	function setURLSegment($value) {
		$urlSegment = URLSegmentFilter::create()->filter($value);

		$i = 1;
		$curr = $urlSegment;
		while($this->existsInScope($curr)) {
			$curr = $urlSegment.$i;
			$i++;
		}
		$this->owner->setField("URLSegment", $curr);
	}

	/**
	 * checks wether the provided param urlSegment exists in the given scope
	 * returns bool
	 */
	function existsInScope($urlSegment) {
		$class = ClassInfo::baseDataClass(get_class($this->owner));

		$list = DataList::create($class);

		//base query
		$list = $list->filter("URLSegment", $urlSegment);

		//check within scope
		$list = $this->addScopeCheck($list);

		//avoid returning itself
		if($id = $this->owner->ID) {
			$list = $list->exclude("ID", $id);
		}

		return (bool)$list->Count();
	}

	function addScopeCheck(DataList $list) {
		$scopeField = $this->Scope;
		if($scopeField && ($scopeValue = $this->owner->$scopeField)) {
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
class URLSegmented_DataListExtension extends Extension {

	function byURL($url) {
		$url_sql = Convert::raw2sql($url);
		$baseClass = ClassInfo::baseDataClass($this->owner->dataClass);

		$sgl = $baseClass::create();
		$required_extension = "URLSegmented";

		if(!$extension_instance = $sgl->getExtensionInstance($required_extension)) {
			trigger_error("{$baseClass} doesnt have the required {$required_extension} extension");
		}
		$list = clone $this->owner;
		$where = "\"{$baseClass}\".\"URLSegment\" = '{$url_sql}'";
		return $list->where($where)->First();
	}

}
