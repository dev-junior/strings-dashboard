<?php

    echo $this->DataTables->output($dataTable,
        function($view,$outputRow,$rawRow) use($isAdmin){

        $formationId = $rawRow['Formation']['id'];
        $formationStatus = $rawRow['Formation']['status'];

        $actionMenu = $view->element('../Formations/_action_menu',array(
            'formationId' => $formationId,
            'actionsDisabled' => (!$isAdmin || $formationStatus !== 'active')
        ));

		//Info link on name column
        $outputRow[0] = $view->Strings->link($outputRow[0],"/Formations/view/$formationId");		

        //Append action menu to last column
        $outputRow[count($outputRow)-1] .= $actionMenu;

        //Add device status as a class
        $outputRow['DT_RowClass'] = "status status-$formationStatus";

        return $outputRow;
    });
