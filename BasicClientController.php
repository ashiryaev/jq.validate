<?php

namespace app\controllers;

/**
 * This is a basic class for any Client implementation. It describes ground properties 
 * and methods for any Client of the system.
 * 
 */

use app\models\Client;
//use app\models\EncoaQuestions;
use app\models\AccountRule;
use \lithium\storage\Session;
use app\models\Market;
use \lithium\net\http\Media;
use \lithium\data\Connections;
//use app\models\EncoaTpvDataCollection;
use li3_access\security\Access;
use flash\extensions\storage\Flash;
use lithium\action\Response;
use app\models\Filter;
use app\extensions\helper\CommonHelper;
use app\services\FreeRestClient;
use app\libraries\question\DataWrapperManager;

abstract class BasicClientController extends \lithium\Action\Controller
{   
    public $client; // Client name indeed
    protected $auth_config; // Current auth_config according to domain name
    protected $user; // Current User information block from Session 
    protected $breadcrumbs;
    
    protected $service;
    
    protected $models = array();
    protected $serviceUrl;
//    /static protected $modelAlias = '\app\models\\';

    protected $freedom_render = array(
                        'paths' => array( 'template' => '{:library}/views/client/{:controller}/{:template}.{:type}.php',
                                            'layout' => '{:library}/views/layouts/{:layout}.{:type}.php',
                                            'element' => '{:library}/views/elements/{:template}.{:type}.php'								
                                	)        
    );     
    
    abstract function reports();
    // all Clients have different reports - override them

    
    /**
     * Initialization method for setting up a Client
     * 
     */   
        
    protected function _init()
    {
        parent::_init();
        
        $auth_config = preg_split("/\./", $_SERVER['SERVER_NAME']);
        $auth_config = $auth_config[0];

        $this->auth_config = $auth_config;
        
        $this->user = Session::Read($this->auth_config);
        //$this->client_slug = $this->request->params['controller'];
        $this->client = Client::first( array( 'conditions' => array(
            'slug' => $this->request->params['controller']
        )));
        
        /* Initiate service object*/
        
        $this->service = new FreeRestClient($this->user['client_slug']);
        
        /* Create Breadcrumbs menu */
        $questionsModelName = CommonHelper::getModelAlias($this->user['client_slug'], 'Questions');
     
        //$this->breadcrumbs = $questionsModelName::getStepNavigationNameByNumber(null, $account_index, null, null, $error_msg);        
        //var_dump($this->breadcrumbs);
        //$this->serviceUrl = "{$this->user['client_slug']}/service";  
        
        /* TODO create all required models and their $names in cycle -  save them to a hash array $models [modelName => modelObject]*/

        
    }    

    public function tpv()
    {   //var_dump($this->request->params); 
        $this->render( array(
            'paths' => $this->freedom_render['paths'],
            'data' => array(
                'client' => $this->client,
                'client_slug' => $this->request->params['controller'],
               // 'breadcrumbs' => $this->breadcrumbs
               // 'roles' => $user['roles']
            ),
        ));
    }      
    
    public function market()
    { 
        //$user = Session::Read($this->request->params['auth_config']);
        $this->user['client_slug'] = $this->request->params['controller'];
        //$user['program'] = $program;
        
        Session::write($this->request->params['auth_config'], $this->user);
        //var_dump(Session::read()); die();
        $this->render( array(
            'paths' => $this->freedom_render['paths'],
            'data' => array(
                'client' => $this->client,
                'client_slug' => $this->request->params['controller'],
               // 'roles' => $user['roles']
            ),
        ));
    }      
        
    /**
     * Getting User information from Session
     */
    
    public function getUser() {
        return Session::Read($this->auth_config);
    }
    
    
    /* DASHBOARD activities */
    
    /*
     * Returns tpv data collections by user and search criteria
     */
    
    public function getTpvList($search_criteria = null){
        //$user = Session::Read($this->request->params['auth_config']);
        
        if(isset($this->user['tpv']))
            unset($this->user['tpv']);

        $modelName = CommonHelper::getModelAlias($this->user['client_slug'], 'TpvDataCollection');
        
        $query_result = $modelName::getTPVinformation($this->user['_id'], 3, $search_criteria);
        foreach($query_result as $tpv ){
            
            $modelName = CommonHelper::getModelAlias($this->user['client_slug'], 'Tpv');
            $outcome = $modelName::getOutComeByReferenceCode($tpv->reference_code); //EncoaTpv::getOutComeByReferenceCode($tpv->reference_code);
            $tpv_list[] = array(
                'clientName' => $tpv->customer_first_name . (isset($tpv->customer_middle_name) ? $tpv->customer_middle_name . ' ': ' ').  $tpv->customer_last_name,
                'address' => $this->getAddressFromTpvEntry($tpv), 
                'referenceCode' => $tpv->reference_code, 
                'outcome' => $outcome[0] );
        }
        
        $this->getDataCollectionStatusesByUser($this->user, $reaffirmed, $declined);
        
        $result = array( 'reaffirmed' => $reaffirmed, 'declined' => $declined);
        
        if(isset($tpv_list))
            $result['tpv_list'] = $tpv_list;
        else
            $result['notice'] = 'No matching records found';
        
        return json_encode($result);
    }
    
    private function getDataCollectionStatusesByUser($user, &$reaffirmed = 0, &$declined = 0){
        
        $modelName = CommonHelper::getModelAlias($this->user['client_slug'], 'TpvDataCollection');
        
        $query_result = $modelName::getTPVinformation($user['_id']);
        
        foreach ($query_result as $tpv){
            $reference_code_list[] = $tpv->reference_code;
        }
        if(!(isset($reference_code_list)))
            return;
        
        $modelName = CommonHelper::getModelAlias($this->user['client_slug'], 'Tpv');
        
        $tpvs = $modelName::getTpvListByReferenceCodeList($reference_code_list); // TODO: get tpv list by user 
        
        if(!(isset($tpvs)))
            return;        
        
        foreach($tpvs as $tpv){
            if(!(isset($tpv->result)))
                continue;
            $result = $tpv->result;
            
            switch ($result){
                case 'success':
                    $reaffirmed ++;
                    break;
                case 'failure':
                    $declined ++;
            }  
        }
    }
    
    private function getAddressFromTpvEntry($tpv){
        
        return isset($tpv->accounts) 
            ?  $tpv->accounts[1]['service_address_number'] 
                . ' '.$tpv->accounts[1]['service_address_street'] 
                . ' '.$tpv->accounts[1]['service_address_extra'] 
                . ' '.$tpv->accounts[1]['service_address_city'] 
                . ' '.$tpv->accounts[1]['service_address_county'] 
                . ' '.$tpv->accounts[1]['service_address_state'] 
                . ' '.$tpv->accounts[1]['service_address_zip'] 
            : '';
    }
    
    /*
     * Restoring state of data collection part by reference code and put everything into session
     */
    public function restoringTpvStateByRefCode($reference_code){
               
        //$user = Session::Read($this->request->params['auth_config']);
        
        $modelName = CommonHelper::getModelAlias($this->user['client_slug'], 'Questions');
        
        $step_number = $modelName::getStepNumberByNavigationName('Submit for TPV');
        
        $modelName = CommonHelper::getModelAlias($this->user['client_slug'], 'TpvDataCollection');
        
        $data_collection = $modelName::getTPVbyReferenceCode($reference_code);
        
        if(isset($this->user['tpv']))
            unset($this->user['tpv']);
        
        $tpv = (object)($data_collection->to('array')); 
        
        for ($i = 1 ; $i <= count($tpv->accounts); $i++ ){ // convert inner array to objects
            $tpv->accounts[$i] = new \stdClass();
            $tpv->accounts[$i] = (object)$data_collection['accounts'][$i]->data();            
        }
        
        $this->user['tpv'] = $tpv; 
        
        $baseUrl = "/$this->user[\'client_slug\']/tpvrequest";
        Session::Write($this->request->params['auth_config'], $this->user);

        $this->redirect($baseUrl.'#step/'.$step_number.'/reloaded/0/loopIndex/0');
    }
    
    public function getOutcomeByReferenceCode($referenceCode){
         
        $modelName = CommonHelper::getModelAlias($this->user['client_slug'], 'Tpv');
        
        $outcome = $modelName::getOutComeByReferenceCode($referenceCode);
         
        return json_encode($outcome);
        
    }
    
    /* end DASHBOARD activities */    
    
    /* RECORDING activities */
    
    /**
     * DataReview method - provides access to the TPVs that passed verifications and their own MP3 soundfiles 
     * 
     */
    
    public function datareview($clientObject = null){
        
        //$user = Session::Read($this->request->params['auth_config']);
        
        $template = 'datareview';
        
        $markets = Market::$states_array;
                
        if( isset($this->request->params['args'][0]) ){
            $template = $this->request->params['args'][0];
            
            $criteria = $this->request->query;
            
            if( isset($criteria['url']) ) {
                unset($criteria['url']);
            }
             
            //$modelName = $this::$modelAlias . ucfirst($this->user['client_slug']) . 'Tpv'; 
            $modelName = CommonHelper::getModelAlias($this->user['client_slug'], 'Tpv');
            $tpvs = $modelName::getTpvListByCriteria($criteria); //EncoaTpv::getTpvListByCriteria($criteria);

        }
        
        $this->render(array(
            'data' => array(
                'client_slug' => $this->user['client_slug'], //$this->request->params['controller'],
                'client' => $this->client,
                'markets' => $markets,
                'tpvs' => isset($tpvs) ? $tpvs->data() : array(),
                'criteria' => isset($criteria) ? $criteria: array()
                ),
            'template' => $template));
        
        
    }    
    
    /**
     * This method provides downloading MP3 record by TPV
     * @param type $filename name of mp3 file
     * @param type $program name of the program (i.e. residential, commercial etc.)
     * @return type downloadable page (i.e. mp3 soundfile)
     */
    
    public function downloadAudioRecord($filename, $program){
        
        //$user = Session::Read($this->request->params['auth_config']);
                
        try{
            $facade = new AudioFileAccessor($this->user['client_slug']);
            $facade->getFileByName($filename, $program);
        }
        catch(Exception $e){
            return json_encode( array('error' => $e));
        }
    }
    
    /* end RECORDING activities */
    
    /**
     *  TPV management page
     */
    
    public function tpvManagement()
    {
        $this->render(array(
            'data' => array(
                'client_slug' => $this->user['client_slug'], //$this->request->params['controller'],
                'client' => $this->client
            )
        ));
    }
    
    
    /**
     *  TPV request page
     */

    public function tpvRequest()
    {
        $this->render(array(
            'data' => array(
                'client_slug' => $this->user['client_slug'], //$this->request->params['controller'],
                'client' => $this->client
            )  
        ));
    }
    
    /**
     * Cancel TPV processing
     */
    public function tpvCancel() {
        $arguments = array(
            'requestData'   => $this->request->data,
            'authConfig'    => $this->auth_config 
        );
        
        $this->service->cancelProcess($this->user, $arguments);
        $this->render(array('type' => 'json', 'data' => array('success' => true)));
    }
    
    /**
     * Agent management method for CRUD operations on Agents
     */
    public function agentManagement() {

        //$user = Session::Read($this->request->params['auth_config']);        
        
        $accessAdmin = Access::check('rba_'.$this->user['client_slug'], $this->user, $this->request, array('rules' => 'isAdmin')); //Access::check('rba_encoa', $user, $this->request, array('rules' => 'isAdmin'));

        if (!empty($accessAdmin)) {                  
            return $this->redirect( '/mainmenu' );
        }       
      
        $agentsArray = array();
        $marketersArray = array();
        $marketersSearch = array();
        
        
        
        $agentsModelName = CommonHelper::getModelAlias($this->user['client_slug'], 'Agent');
               
        //$agentsModelName1 = $this::$modelAlias . ucfirst($this->user['client_slug']) . 'Agent'; 

        $agents = $agentsModelName::all( //EncoaAgent::all(
            array (
             'conditions' => $this->user['conditions']
            )    
        );
       
        $marketersModelName = CommonHelper::getModelAlias($this->user['client_slug'], 'Marketer');
        
        //$marketersModelName = $this::$modelAlias . ucfirst($this->user['client_slug']) . 'Agent'; 
        $marketers = $marketersModelName::all( array( //EncoaMarketer::all(array(
             'conditions' => $this->user['conditions']
        ));    
        foreach($marketers->data() as $marketer)
        {
            // set data to return to view
            $marketersArray[] = array('Text' => $marketer['marketer_name'], 'Value' => $marketer['marketer_id']);
            // make a simple array for associating marketer name with agent via marketer id
            $marketersSearch[$marketer['marketer_id']] = $marketer['marketer_name'];
        }
        
        // put agents into an array
        foreach($agents->data() as $agent)
        {
            if( !isset($agent['status']) ){
                $agent['status'] = 'Active';
            }
            
            // not sure if mongodb has relationship function
            // doing this manually for now...
            if( isset($agent['marketer_id']) && isset($marketersSearch[$agent['marketer_id']]) ){
                $agent['marketer'] = $marketersSearch[$agent['marketer_id']];
            }
            else{
                $agent['marketer_id'] = '';
                $agent['marketer'] = '';
            }

            $agentsArray[] = $agent;
        }
        
        //$user = Session::Read($this->request->params['auth_config']);        
        
        $this->render(array(
            'data' => array(
                'client_slug' => $this->user['client_slug'], //$this->request->params['controller'],
                'client' => $this->client,
                'results' => $agentsArray,
                'marketers' => $marketersArray
            ),
        ));
        
    }   

    
    /**
     *  Pricing management method for CRUD operations on Prices and tariffs
     */
    
    public function pricingUpdate() {
        //$user = Session::Read($this->request->params['auth_config']);
        
        $accessAdmin = Access::check('rba_'.$this->user['client_slug'], $this->user, $this->request, array('rules' => 'isAdmin'));
        
        if (!empty($accessAdmin)) {                  
            return $this->redirect('/mainmenu');
        }       
        /* Available for Encoa only */
        
        $productsModelName = CommonHelper::getModelAlias($this->user['client_slug'], 'Products'); //$this::$modelAlias . ucfirst($this->user['client_slug']) . 'Products'; 
        $products = $productsModelName::all(); //EncoaProducts::all();
        $productsArray = array(); 
        
        foreach ($products->data() as $value) {
            if(!isset($value['commertial_programm'])){
                $value['commertial_programm'] = '';
            }
            $productsArray[] = $value;
        }
        $this->render(array(
            'data' => array(
                'client_slug' => $this->user['client_slug'], //$this->request->params['controller'],
                'client' => $this->client,
                'products' => $productsArray
            ),
        ));        
    }
    

    /*
     * WEB SERVICE FOR QUESTIONS
     */
    public function service ($step = 0, $type = 'json', $is_reload = 'false', $account_index = 0) // service($step = 0, $type = 'json', $is_reload = 'false', $account_index = 0)
    {       
       
        //$baseUrl = "{$this->user['client_slug']}/service";
       
        $user = $this->user;
        // save current step number in session
        $auth_config = $this->request->params['auth_config'];
        $user["current_step"] = $step;
        $request_data = $this->request->data;
        
        //$user = Session::Read($this->auth_config);
        
        $questionsModelName = CommonHelper::getModelAlias($this->user['client_slug'], 'Questions');
      
        $current_step = $questionsModelName::getQuestion(
                $step, isset($user['tpv']->market) ? $user['tpv']->market : null, 
                isset($user['product']->utility) ? $user['product']->utility : null, 
                $error_msg);
        $title = $questionsModelName::getStepNavigationNameByNumber($step, $account_index, null, null, $error_msg);

        $arguments = compact('auth_config', 'step', 'type', 'is_reload', 'account_index', 'current_step', 'request_data', 'error_msg');
        // TODO: $accept ???
        $accept = 1;
        $response = $this->service->handleRequest($this->request->method, $user, $arguments, $accept);        

        $this->runResponse($response, $title); 
    }

    
    private function runResponse($response) {
        //var_dump($response['args']['data']);
        /* Parse response container */
        switch ($response['action']) {
            case 'render': 
                $this->render( array(
                        'type' => $response['args']['type'],  
                        'data' => $response['args']['data']
                       )); 
                return;
            case 'redirect':
                $this->redirect($response['args']['url'], array('exit' => true));
                return;
                
            default:
                return;
        }
        //return;
         
    } 
        
    /*
     * WEB SERVICE WHICH PROVIDES ACCOUNT RULES (Account number lenght etc.)
     */
    public function getAccoutRules($utility_name)
    {

       $account_nuber_length =  
        AccountRule::getAccountNumberLengthByUtilityName($utility_name, $error_msg);
    
       if($error_msg != null)
           return json_encode(array('notification' => $error_msg));           
      
       return json_encode(array( 'account_number_length' => $account_nuber_length)); 
    }
    
    
    /** Helper method to restore step info if the process is already started in session
     *
     * @param type $account_index
     * @param type $inputs
     * @return type 
     */
        
    public function getDataForStepRestore($account_index = 0, $inputs = null){
        //print 'hello'; die();
        //$user = Session::Read($this->request->params['auth_config']);
        if($inputs == null)
            return json_encode(array('notification' => 'second parameter is missed'));
        
        $input_name_list = explode('|', $inputs);
        $result = array();
        
        if(!isset($this->user['tpv']))
                return json_encode(array('notification' => 'tpv is not set in session'));
        
               
        foreach($input_name_list as $input_name){
            if($input_name == '')
                continue;
            if($account_index > 0){
                $result[] = array( $input_name => isset($this->user['tpv']->accounts[$account_index]->$input_name) ? $this->user['tpv']->accounts[$account_index]->$input_name : '');
            }else {
                $result[] = array( $input_name => isset($this->user['tpv']->$input_name) ? $this->user['tpv']->$input_name : '');
            }      
        }
        
        return json_encode($result);        
    }
    
    /**
     * Gets data for dynamic selects or inputs 
     */
    public function getQuestionDynamicData($wrapper, $method) {
       $parameters = array();
       
       if (!empty($this->request->query['parameters'])) {
           $parameters = $this->request->query['parameters'];
       }
       
       return json_encode(DataWrapperManager::getData($wrapper, $method, $parameters));
    }
    
    /** Preserved - currently NOT in USE  */
    
    private function setProductToSession( $product )
    {
        
        $user = Session::Read($this->request->params['auth_config']);
        
        if( isset($user['product']) )
        {
            unset($user['product']);
        }
        
        $user['product'] = new \stdClass();
	$user['product']->code = $product->code;
        $user['product']->utility = $product->utility;
        $user['product']->rate = $product->rate;
        $user['product']->program = $product->program;
        
        Session::Write($this->request->params['auth_config'], $user);
    }  

}
www