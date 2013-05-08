<?php

App::uses('Model', 'Model');

class AppModel extends Model {

	public $actsAs = array(
		'Containable',
		'Linkable',
		'ExtendAssociations2'
	); 

	/**
	 * Validate relationship (foreign_key) between this model and belongsTo model
 	 */
	public function isValidForeignKey($data) {
    	foreach ($data as $key => $value) {
        	foreach ($this->belongsTo as $alias => $assoc) {
            	if ($assoc['foreignKey'] == $key) {
                	$this->{$alias}->id = $value;
                	return $this->{$alias}->exists();
            	}
        	}
    	}
    	return false;
	}

	/**
	 * Validate multikey unique constraint
	 */
	public function checkMultiKeyUniqueness($data, $fields){
	
		// check if the param contains multiple columns or a single one
		if (!is_array($fields))
			$fields = array($fields);
		 
		// go through all columns and get their values from the parameters
		foreach($fields as $key)
			$unique[$key] = $this->data[$this->name][$key];
		 
		// primary key value must be different from the posted value
		if (isset($this->data[$this->name][$this->primaryKey]))
			$unique[$this->primaryKey] = "<>" . $this->data[$this->name][$this->primaryKey];
		 
		// use the model's isUnique function to check the unique rule
		return $this->isUnique($unique, false);
	}

	/**
     * Get validationErrors as a string
     *
     * @return string The validation errors data structure converted to a string
     */
    public function validationErrorsAsString(){

        $message = "";
        foreach($this->validationErrors as $field => $fieldErrorMessages){
			array_walk_recursive($fieldErrorMessages,function(&$element,$index) use($field) {
				$element = str_replace('%f',$field,$element);
				$element = ucfirst($element);
			});
            $message .= implode(". ",$fieldErrorMessages) . ". ";
		}
        return $message;
    }
}
