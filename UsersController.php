<?php
namespace app\controllers;

use lithium\security\Auth;
use app\models\Personnel;
use app\models\Client;

class UsersController extends \lithium\action\Controller {

    /**
    * Array of public functions that can be
    * access without being logged in
    *
    * @var array
    */
    public $publicActions = array('login');

    //Basic validation
    public $validates = array(
        'username' => array(
            array('notEmpty', 'message' => 'You must include a username.')
        ),
        'password' => array(
            array('notEmpty', 'message' => 'You must include a password.')
        ),
    );

    /**
     * Add user function
     * Not yet implemented.
     *
     * @return
     */
    public function add(){
            $user = Users::create();
            if(!empty($this->request->data)){
                    if($user->save($this->request->data)){
                            //Auto-login the user
                            Auth::check('personnel', $this->request);
                            return $this->redirect('/dashboard');
                    }
            }

            return compact('personnel');
    }
}
?>

bbb
