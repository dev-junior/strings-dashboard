<?php

App::uses('Component', 'Controller');

class FormationsAndDevicesComponent extends Component {

    /*
     * The controller that is utilizing this component
     */
    public $controller;

    public function initialize($controller){

        $this->controller = $controller;
    }

    private function _loadModel($model){
        $this->controller->loadModel($model);
    }

    public function parseAndValidateDevice($device, $input){

        $this->_loadModel('Config');

        $errors = array();
        $models = array();

        $deviceType = $device['DeviceType']['name'];

        if(isset($device['Device']['psuedo']))
            $device = $this->deviceFromPsuedoDevice($device);

        if($deviceType == "instance")
            list($errors, $models) = $this->parseAndValidateInstance($device, $input);
        elseif($deviceType == "load-balancer")
            list($errors, $models) = $this->parseAndValidateLoadBalancer($device, $input);
        else
            throw new Exception('Unexpected device type');

        return array($errors, $models);
    }

    public function deviceFromPsuedoDevice($device){

        return array(
            'Device' => array(
                'organization_id' => $device['Device']['organization_id'],
                'device_type_id' => $device['Device']['device_type_id'],
                'implementation_id' => $device['Device']['implementation_id'],
                'blueprint_part_id' => $device['Device']['blueprint_part_id'],
                'role_id' => $device['Device']['role_id'],
                'environment_id' => $device['Device']['environment_id'],
                'name' => $device['Device']['name']
        ));
    }

    public function parseAndValidateInstance($device, $input){

        $this->_loadModel('Implementation');
        $this->_loadModel('Role');

        $errors = array();

        $device['DeviceAttribute'] = array();

        $implementationId = $device['Device']['implementation_id'];

        //Region
        list($regionError, $regionId, $regionName) = $this->parseAndValidateRegion($device, $input);
        if(!empty($regionError)){
            $errors['infrastructure'][] = $regionError;
            return array($errors, $device);
        }
        else {
            $device['DeviceAttribute'][] = array(
                'var' => 'implementation.region_id',
                'val' => $regionId
            );
        }

        //Dns
        $deviceName = $device['Device']['name'];
        list($intDnsSuffix,$extDnsSuffix) = $this->getDnsSuffixes();
        $deviceInternalFqdn = strtolower("$deviceName.$regionName.$intDnsSuffix");
        $deviceExternalFqdn = strtolower("$deviceName.$regionName.$extDnsSuffix");
        $device['DeviceAttribute'][] = array(
            'var' => 'dns.internal.fqdn',
            'val' => $deviceInternalFqdn
        );

        $device['DeviceAttribute'][] = array(
            'var' => 'dns.external.fqdn',
            'val' => $deviceExternalFqdn
        );

        //Image id
        $imageId = $this->getImage($device, $regionId);
        if(empty($imageId)){
            $errors['infrastructure'][] = "Could not find an image for $regionName.";
            return array($errors, $device);
        }
        else {
            $device['DeviceAttribute'][] = array(
                'var' => 'implementation.image_id',
                'val' => $imageId
            );
        }

        //Flavor
        static $flavorIds = array();
        if(empty($flavorIds)){
            $flavorIds = array_keys($this->getProviderFlavors($implementationId));
        }

        if(!isset($input['flavor']) || empty($input['flavor']))
            $errors['infrastructure'][] = 'Flavor is required.';
        elseif(!in_array($input['flavor'],$flavorIds))
            $errors['infrastructure'][] = 'Invalid flavor.';
        else {
            $device['DeviceAttribute'][] = array(
                'var' => 'implementation.flavor_id',
                'val' => $input['flavor']
            );
        }

        //Validate instance variables
        $variablesInput = isset($input['variables']) ? $input['variables'] : array();
        list($systemErrors, $hieraVariables) = $this->parseAndValidateInstanceVariables(
            $device,
            $variablesInput
        );

        if(!empty($hieraVariables))
            $device['HieraVariable'] = $hieraVariables;

        //Set system errors
        if(!empty($systemErrors))
            $errors['system'] = $systemErrors;

        return array(
            $errors,
            $device
        );
    }

    public function parseAndValidateRegion($device, $input){

        $error = false;

        $regionId = false;
        $regionName = false;

        $implementationId = $device['Device']['implementation_id'];
        static $regions = array();
        static $regionIds = array();
        if(empty($regions) || empty($regionIds)){
            $regions = $this->getProviderRegions($implementationId);
            $regionIds = array_keys($regions);
        }

        if(!isset($input['region']) || empty($input['region']))
            $error = 'Region is required.';
        elseif(!in_array($input['region'],$regionIds)){
            $error = 'Invalid region.';
        }
        else {
            $regionId = $input['region'];
            $regionName = $regions[$regionId];
        }

        return array($error, $regionId, $regionName);
    }

    public function getImage($device, $regionId){

        $imageId = false;

        $implementationId = $device['Device']['implementation_id'];
        static $images = array();
        if(empty($images)){
            $images = $this->getProviderImages($implementationId);
        }

        $regionImages = Hash::combine($images[$regionId],'{n}.name','{n}.image_id');

        if(!isset($regionImages['default'])){
            return false;
        }
        else {
            return $regionImages['default'];
        }
    }

    public function parseAndValidateInstanceVariables($device, $input){

        $this->_loadModel('HieraVariable');
        $this->_loadModel('Role');

        //Get the vars for this role
        static $cachedInstanceVars;
        $roleId = $device['Device']['role_id'];
        if(!isset($cachedInstanceVars[$roleId])){
            $cachedInstanceVars[$roleId] = $this->controller->Role->getRoleVariables($roleId);
        }
        $instanceVarDefs = $cachedInstanceVars[$roleId];

        //Get external FQDN; req'd for hiera key
        $deviceFqdn = "";
        $deviceAttrs = $device['DeviceAttribute'];
        foreach($deviceAttrs as $attr){
            if($attr['var'] == 'dns.external.fqdn')
                $deviceFqdn = $attr['val'];
        }
        if(empty($deviceFqdn))
            throw new InternalErrorException('Could not determine devices FQDN.');

        $hieraKey = "fqdn/$deviceFqdn";

        list($errors, $variables) =
            $this->_parseAndValidateInstanceVariables($instanceVarDefs,$input,$hieraKey);

        return array($errors, $variables);
    }

    private function _parseAndValidateInstanceVariables($modulesVariables,$input,$hieraKey){

        $variables = array();
        $errors = array();

        foreach($modulesVariables as $moduleId => $module){

            $moduleErrors = array();
            $moduleShortName = $module['shortName'];

            foreach($module['variables'] as $varId => $var){

                $hieraVar = $var['var'];
                $varName = $var['name'];
                $varFullName = ucfirst($moduleShortName) . " " . ucfirst($varName);
                $varValidationPattern = $var['validation_pattern'];

                //Check if variable is set
                if(isset($input[$moduleId]) && isset($input[$moduleId][$varId]) && 
                   (is_numeric($input[$moduleId][$varId]) || !empty($input[$moduleId][$varId]))){

                    $deviceVarVal = $input[$moduleId][$varId];

                    //If variable is not editable, overwrite input value with
                    //the default value
                    if(!$var['is_editable']){
                        $deviceVarVal = $var['default_value'];
                    }

                    //Validate variable value if needed
                    if(!empty($varValidationPattern) && !preg_match($varValidationPattern,$deviceVarVal))
                        $moduleErrors[$varId] = "Invalid value.";
                    else {
                        $variables[] = array(
                            'hiera_key' => $hieraKey,
                            'var' => $hieraVar,
                            'val' => $deviceVarVal
                        );
                    }
                }
                else { //Verify variable is not required
                    if($var['is_required'])
                        $moduleErrors[$varId] = "Variable is required.";
                }
            }

            if(!empty($moduleErrors))
                $errors[$moduleId] = $moduleErrors;
        }

        return array($errors, $variables);
    }

    public function parseAndValidateLoadBalancer($device,$input){

        $this->_loadModel('Implementation');

        $implementationId = $device['Device']['implementation_id'];

        //Get provider
        static $cachedImplementations = array();
        if(!isset($cachedImplementations[$implementationId])){

            $implementation = $this->controller->Implementation->find('first', array(
                'contain' => array(
                    'Provider'
                ),
                'conditions' => array(
                    'Implementation.id' => $implementationId
                )
            ));

            if(empty($implementation))
                throw new Exception('Could not find implementation');

            $cachedImplementations[$implementationId] = $implementation;
        }
        $provider = $cachedImplementations[$implementationId];
        $providerName = strtolower($provider['Provider']['name']);

        if($providerName == 'rackspace'){
            return $this->parseAndValidateRackspaceLoadBalancer($provider, $device, $input);
        }
        else {
            throw new Exception('Unrecognized load-balancer provider');
        }

    }
    
    public function parseAndValidateRackspaceLoadBalancer($provider, $device, $input){

        $errors = array();

        $device['DeviceAttribute'] = array();

        $providerId = $provider['Provider']['id'];

        /*
         * Rackspace only supports one protocol/port pair per load-balancer.
         * As a result, we spin up one device per protocol/port pair.
         */
        $devices = array();

        //Region
        list($regionError, $regionId, $regionName) = $this->parseAndValidateRegion($device, $input);
        if(!empty($regionError)){
            $errors['infrastructure'][] = $regionError;
            return array($errors, $device);
        }
        else {
            $device['DeviceAttribute'][] = array(
                'var' => 'implementation.region_id',
                'val' => $regionId
            );
        }

        //Check for required input
        $requiredAttrs = array(
            'virtualIpType' => 'Virtual IP',
            'algorithm' => 'Algorithm',
            'protocol' => 'Protocol',
            'port' => 'Port'
        );

        foreach($requiredAttrs as $attrName => $friendlyName){
            if(!isset($input[$attrName]) || empty($input[$attrName]))
                $errors['load-balancer'] = $friendlyName . " is required.";
        }
            
        //Validate atttributes
        $lbAttrs = array(
            'virtualIpType' => 'implementation.virtual_ip_type',
            'algorithm' => 'implementation.algorithm',
        );

        foreach($lbAttrs as $attrName => $attrVar){
            $attrVal = $input[$attrName];
            list($valid,
                 $msg) = $this->validateRackspaceLoadBalancerAttribute($providerId,
                                                                        $attrName,
                                                                        $attrVal);
            if(!$valid)
                $errors['load-balancer'][] = $msg;
            else {
                $device['DeviceAttribute'][] = array(
                    'var' => $attrVar,
                    'val' => $attrVal
                );
            }
        }

        //Validate Protocol/port pairs
        $protocolPortPairs = array();
        if(count($input['protocol']) < 1 || count($input['port']) < 1){
            $errors['load-balancer'][] = 'At least one protocol/port pair is required.'; 
        }
        else {

            //Build a list of protocol/port pairs
            for($x = 0; $x < count($input['protocol']); $x++){
                $protocol = $input['protocol'][$x];
                $port = $input['port'][$x];

                //Protocol would be empty if the user selected 'none'
                //for the additional protocol/port pair
                if(empty($protocol))
                    continue;

                if(empty($port)){
                    $errors['load-balancer'][] = "A port is required for protocol $protocol.";
                    continue;
                }

                $ppKey = "$protocol|$port";
                if(isset($protocolPortPairs[$ppKey])){
                    $errors['load-balancer'][] = "Each protocol/port pair must be unqiue.";
                }

                $protocolPortPairs[$ppKey] = array($protocol, $port);
            }
        }
        if(empty($errors)){

            $moreThanOneLB = count($protocolPortPairs) > 1;
            $nameIncr = 1;

            foreach($protocolPortPairs as $pair){

                list($protocol, $port) = $pair;

                list($validProtocol,
                     $protoErrMsg) = $this->validateRackspaceLoadBalancerAttribute($providerId,
                                                                                    'protocol',
                                                                                    $protocol);
                if(!$validProtocol)
                    $errors['load-balancer'][] = $protoErrMsg;

                list($validPort,
                    $portErrMsg) = $this->validateRackspaceLoadBalancerAttribute($providerId,
                                                                                'port',
                                                                                $port);
                if(!$validPort)
                    $errors['load-balancer'][] = $portErrMsg;


                if($validProtocol && $validPort) {

                    $deviceClone = $device;

                    if($moreThanOneLB){

                        //Add a numberical index to the names
                        $nameSuffix = str_pad($nameIncr,2,0,STR_PAD_LEFT); 
                        $deviceClone['Device']['name'] .= $nameSuffix;

                        //If this is not the first LB, set the peer LB 
                        //attribute so the API knows to setup the shared
                        //VIP for this LB
                        if($nameIncr > 1){
                            $deviceClone['DeviceAttribute'][] = array(
                                'var' => 'implementation.peer_lb',
                                'val' => $device['Device']['name'] . "01"
                            );
                        }

                        $nameIncr++;
                    }

                    $deviceClone['DeviceAttribute'][] = array(
                        'var' => 'implementation.protocol',
                        'val' => $protocol
                    );
                    $deviceClone['DeviceAttribute'][] = array(
                        'var' => 'implementation.port',
                        'val' => $port
                    );

                    //If session persistence is enabled, set appropriately for this protocol
                    if(isset($input['sessionPersistence']) && $input['sessionPersistence']){
                        $sessionPersistenceType = $this->rackspaceLBProtocolToSessionPersistenceType($protocol);
                        $deviceClone['DeviceAttribute'][] = array(
                            'var' => 'implementation.session_persistence',
                            'val' => $sessionPersistenceType
                        );
                    }

                    $devices[] = $deviceClone;
                }
            }
        }

        return array($errors, $devices);
    }

    private function rackspaceLBProtocolToSessionPersistenceType($protocol){

        if($protocol == "HTTP"){
            return "HTTP_COOKIE";
        }
        else {
            return "SOURCE_IP";
        }
    }

    public function validateRackspaceLoadBalancerAttribute($providerId, $attribute, $value){

        $this->_loadModel('Provider');

        static $cachedValidAttrs = false;
        static $validVirtualIpTypes = array();
        static $validAlgorithms = array();
        static $validProtocols = array();
        if(!$cachedValidAttrs){
            $validAttrs = $this->controller->Provider->getLoadbalancerAttributes($providerId);

            //VIP type
            $validVirtualIpTypes = json_decode(
                $validAttrs['load_balancers.virtual_ip_types'],
                true
            );

            //Algorithms
            $validAlgorithms = json_decode(
                $validAttrs['load_balancers.algorithms'],
                true
            );
            $validAlgorithms = Hash::extract($validAlgorithms,'{n}.name');

            //Protocols
            $validProtocols = json_decode(
                $validAttrs['load_balancers.protocols'],
                true
            );
            $validProtocols = Hash::extract($validProtocols,'{n}.name');

            $cachedValidAttrs = true;
        }

        if($attribute == 'virtualIpType'){
            if(empty($value)){
                return array(
                    false,
                    "Virtual IP is required."
                );
            }
            if(!in_array($value,$validVirtualIpTypes)){
                return array(
                    false,
                    "Invalid virtual ip type supplied."
                );
            }
        }
        elseif($attribute == 'algorithm'){
            if(empty($value)){
                return array(
                    false,
                    "Algorithm is required."
                );
            }
            if(!in_array($value, $validAlgorithms)){
                return array(
                    false,
                    "Invalid algorithm supplied."
                );
            }
        }
        elseif($attribute == 'protocol'){
            if(empty($value)){
                return array(
                    false,
                    "Protocol is required."
                );
            }
            if(!in_array($value, $validProtocols)){
                return array(
                    false,
                    "Invalid protocol supplied."
                );
            }
        }
        elseif($attribute == 'port'){
            if(empty($value)){
                return array(
                    false,
                    "Port is required."
                );
            }
            if(!is_numeric($value) || $value < 1 || $value > 65535){
                return array(
                    false,
                    "Invalid port supplied. Port should be an integer between 1 and 65535."
                );
            }
        }
        else {
            throw new Exception('Unexpected attribute');
        }

        return array(true, null);
    }

    public function getDeviceFormData($device){

        $formData = array();

        $implementationId = $device['Device']['implementation_id'];
            
        //Get provider regions
        //All devices need a region
        $formData['regions'] = $this->getProviderRegions($implementationId);

        $deviceType = $device['DeviceType']['name'];
        $deviceTypeFormData = array();
        if($deviceType == 'instance'){
            $deviceTypeFormData = $this->getInstanceFormData($device);
        }
        elseif($deviceType == 'load-balancer'){
            $deviceTypeFormData = $this->getLoadBalancerFormData($implementationId);
        }
        else {
            throw new Exception('Unexpected device type');
        }
        $formData = array_merge($formData,$deviceTypeFormData);

        return $formData;
    }

    public function getInstanceFormData($device){

        $this->_loadModel('Role');

        $formData = array();

        $implementationId = $device['Device']['implementation_id'];

        //Flavors
        $flavors = $this->getProviderFlavors($implementationId);
        $formData['flavors'] = $flavors;

        //Instance variables
        $roleId = $device['Device']['role_id'];
        $varDefs = $this->getRoleVariables($roleId);
        $formData['varDefs'] = $varDefs;

        return $formData;
    }

    public function getRoleVariables($roleId){

        static $cachedVarDefs = array();

        if(!isset($cachedVarDefs[$roleId])){
            $cachedVarDefs[$roleId] = 
                $this->controller->Role->getRoleVariables($roleId);
        }

        return $cachedVarDefs[$roleId];
    }

    public function getLoadBalancerFormData($implementationId){

        $this->_loadModel('Implementation');

        $formData = array();

        $implementation = $this->controller->Implementation->findById($implementationId);
        $providerId = $implementation['Implementation']['provider_id'];

        $attrs = $this->controller->Implementation->Provider->getLoadbalancerAttributes($providerId);

        //virtual ip types
        $virtualIpTypes = json_decode($attrs['load_balancers.virtual_ip_types']);
        $formData['virtualIpTypes'] = Hash::combine($virtualIpTypes,'{n}','{n}');

        //protocols
        $protocols = json_decode($attrs['load_balancers.protocols']);
        $formData['protocolPortMap'] = Hash::combine($protocols,'{n}.name','{n}.port');
        $formData['protocols'] = Hash::combine($protocols,'{n}.name','{n}.name');
        array_walk($formData['protocols'],function(&$name){
            $name = Inflector::humanize($name);
        });

        //algorithms
        $algorithms = json_decode($attrs['load_balancers.algorithms']);
        $formData['algorithms'] = Hash::combine($algorithms,'{n}.name','{n}.name');
        array_walk($formData['algorithms'],function(&$name){
            $name = Inflector::humanize(strtolower($name));
        });

        return $formData;
    }

    public function getDnsSuffixes(){
        static $dnsSuffixes = array();
        if(empty($dnsSuffixes))
            $dnsSuffixes = $this->controller->Config->getDnsSuffixes();
        return $dnsSuffixes;
    }

    public function getProviderRegionsAndFlavors($implementationId){

        $regions = $this->getProviderRegions($implementationId);
        $flavors = $this->getProviderFlavors($implementationId);

        return array($regions,$flavors);
    }

    public function getProviderRegions($implementationId){

        static $regions = array();

        if(!empty($regions))
            return $regions;

        $this->_loadModel('Implementation');

        //Get list of provider regions
        $regions = $this->controller->Implementation->getRegions($implementationId);
        if(empty($regions))
            throw new InternalErrorException('Regions have not been defined for this provider');
        $regions = Hash::combine($regions,'{n}.id','{n}.name');

        return $regions;
    }

    public function getProviderFlavors($implementationId){

        static $flavors = array();

        if(!empty($flavors))
            return $flavors;

        $this->_loadModel('Implementation');

        //Get a list of provider flavors
        $flavors = $this->controller->Implementation->getFlavors($implementationId);
        if(empty($flavors))
            throw new InternalErrorException('Flavors have not been defined for this provider');
        $flavors = Hash::combine($flavors,'{n}.id','{n}.description');

        return $flavors;
    }

    public function getProviderImages($implementationId){

        static $images = array();

        if(!empty($images))
            return $images;

        $this->_loadModel('Implementation');

        $images = $this->controller->Implementation->getImages($implementationId);
        if(empty($images))
            throw new InternalErrorException('Images have not been defined for this provider');

        return $images;
    }
}
