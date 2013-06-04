<?php

class FormationsController extends AppController
{

    public function beforeFilter(){

        $this->Wizard->steps = array('blueprint','instanceCount','configureInstances','confirmation');
    }

	/**
     * Home screen containing list of formation and create formation CTA
     */
    public function index() {

		//Verify this organization has setup one or more infrastructure providers
        $isInfraProviderConfigured = $this->Formation->Device->Implementation->hasOrganizationConfiguredServiceProvider($this->Auth->user('organization_id'),'infrastructure');
        if(!$isInfraProviderConfigured){
            $this->Session->setFlash(__('Please setup an infrastructure provider <a href="#">here</a>.'),'default',array(),'error');
        }

        $formationTableColumns = array(
            'Name' => array(
                'model' => 'Formation',
                'column' => 'name'
            )
        );

        if($this->request->isAjax()){

            //Datatables
            $findParameters = array(
                'fields' => array(
                    'Formation.id','Formation.name'
                ),
                'conditions' => array(
                    'Formation.organization_id' => $this->Auth->user('organization_id')
                )
            );

            $dataTable = $this->DataTables->getDataTable($formationTableColumns,$findParameters);

            $this->set(array(
                'dataTable' => $dataTable,
                'isAdmin' => $this->Auth->User('is_admin')
            ));
        }
        else {
            $this->set(array(
                'formationTableColumns' => array_keys($formationTableColumns),
				'isInfraProviderConfigured' => $isInfraProviderConfigured
            ));
        }
    }

    public function wizard($step=null){

        $this->Wizard->process($step);
    }

    public function _processBlueprint(){

    }

    public function searchByName(){

        $this->autoRender = false;

        $search = $this->request->query['term'];

        $formations = $this->Formation->find('all',array(
            'fields' => array(
                'Formation.id','Formation.name'
            ),
            'conditions' => array(
                'Formation.name LIKE' => "%$search%"
            )
        ));

        foreach($formations as $index => $formation){
            $formations[$index] = $formation['Formation']['name'];
        }

        echo json_encode($formations);
    }
}
