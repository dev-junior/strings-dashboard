<?php

class SudoRole extends AppModel {

	public $useTable = 'sudo';

	public $belongsTo = array(
		'Organization'
	);

	public $hasMany = array(
		'SudoAttribute' => array(
			'foreignKey' => 'sudo_id'
		)
	);

	public $hasAndBelongsToMany = array();

	public $validate = array(
        'organization_id' => array(
            'requiredOnCreate' => array(
                'rule' => 'notEmpty',
                'on' => 'create',
                'required' => true,
                'message' => '%%f is required'
            ),
            'notEmpty' => array(
                'rule' => 'notEmpty',
                'message' => '%%f cannot be empty'
            ),
            'isNumeric' => array(
                'rule' => 'numeric',
                'message' => '%%f must be an integer'
            ),
			'validForeignKey' => array(
				'rule' => array('isValidForeignKey'),
				'message' => '%%f does not exist'
			)
        ),
        'name' => array(
            'requiredOnCreate' => array(
                'rule' => 'notEmpty',
                'on' => 'create',
                'required' => true,
                'message' => '%%f is required'
            ),
            'notEmpty' => array(
                'rule' => 'notEmpty',
                'message' => '%%f cannot be empty'
            ),
            'validName' => array(
                'rule' => array('custom','/[A-Za-z0-9-_\. @]{3,}/'),
                'message' => '%%f is limited to letters, numbers and punctuation and must be at least 3 characters long'
            ),
			'checkMultiKeyUniqueness' => array(
				'rule' => array('checkMultiKeyUniqueness',array('name','organization_id')),
				'message' => 'This %%f is already taken'
			)
        )
    );

	public function beforeFind($query){

		//Ignore hidden records
		if(isset($query['conditions'])){
			$query['conditions'] = array(
				'AND' => array(
					'SudoRole.is_hidden' => 0,
					$query['conditions']
				)
			);
		}
		else {
			$query['conditions'] = array('SudoRole.is_hidden' => 0);
		}

		return $query;
	}

}
