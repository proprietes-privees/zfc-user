<?php

namespace ZfcUser\Authentication\Adapter;

use DateTime;
use Zend\Authentication\Result as AuthenticationResult;
use Zend\ServiceManager\ServiceManagerAwareInterface;
use Zend\ServiceManager\ServiceManager;
use Zend\Crypt\Password\Bcrypt;
use Zend\Session\Container as SessionContainer;
use ZfcUser\Authentication\Adapter\AdapterChainEvent as AuthEvent;
use ZfcUser\Mapper\User as UserMapperInterface;
use ZfcUser\Options\AuthenticationOptionsInterface;

class Db extends AbstractAdapter implements ServiceManagerAwareInterface
{
    /**
     * @var UserMapperInterface
     */
    protected $mapper;

    /**
     * @var closure / invokable object
     */
    protected $credentialPreprocessor;

    /**
     * @var ServiceManager
     */
    protected $serviceManager;

    /**
     * @var AuthenticationOptionsInterface
     */
    protected $options;

    /**
     * Called when user id logged out
     * @param  AuthEvent $e event passed
     */
    public function logout(AuthEvent $e)
    {
        $session = new SessionContainer($this->getStorage()->getNameSpace());
        $session->getManager()->destroy();

		$container = new SessionContainer('entity');
		$container->getManager()->destroy();
    }

    public function authenticate(AuthEvent $e)
    {
        if ($this->isSatisfied()) {
            $storage = $this->getStorage()->read();
            $e->setIdentity($storage['identity'])
              ->setCode(AuthenticationResult::SUCCESS)
              ->setMessages(array('Authentication successful.'));
            return;
        }

        $identity   = $e->getRequest()->getPost()->get('identity');
        $credential = $e->getRequest()->getPost()->get('credential');
        $credential = $this->preProcessCredential($credential);
        $userObject = null;

        // Cycle through the configured identity sources and test each
        $fields = $this->getOptions()->getAuthIdentityFields();
        while (!is_object($userObject) && count($fields) > 0) {
            $mode = array_shift($fields);
            switch ($mode) {
                case 'code':
                    $userObject = $this->getMapper()->findByCode($identity);
                    break;
                case 'email':
                    $userObject = $this->getMapper()->findByEmail($identity);
                    break;
            }
        }

        if (!$userObject) {
            $e->setCode(AuthenticationResult::FAILURE_IDENTITY_NOT_FOUND)
              ->setMessages(array("Ce compte n'existe pas."));
            $this->setSatisfied(false);
            return false;
        }

        if ($this->getOptions()->getEnableUserActive()) {
            // Don't allow user to login if active is not in allowed list
            if (!in_array($userObject->isValid(), $this->getOptions()->getAllowedLoginActives())) {
                $e->setCode(AuthenticationResult::FAILURE_UNCATEGORIZED)
                  ->setMessages(array("Ce compte n'est plus actif."));
                $this->setSatisfied(false);
                return false;
            }
        }

        /**
		 * @todo change password process
		 */
        /*$bcrypt = new Bcrypt();
        $bcrypt->setCost($this->getOptions()->getPasswordCost());
        if (!$bcrypt->verify($credential, $userObject->getPassword())) {
            // Password does not match
            $e->setCode(AuthenticationResult::FAILURE_CREDENTIAL_INVALID)
              ->setMessages(array('Supplied credential is invalid.'));
            $this->setSatisfied(false);
            return false;
        }*/

        if(!$userObject->hashPassword($userObject, $credential))
		{
			// Password does not match
            $e->setCode(AuthenticationResult::FAILURE_CREDENTIAL_INVALID)
              ->setMessages(array('Mot de passe incorect.'));
            $this->setSatisfied(false);
            return false;
		}

        // regen the id
        $session = new SessionContainer($this->getStorage()->getNameSpace());
        $session->getManager()->regenerateId();

        // Success!
        $e->setIdentity($userObject->getId());
        // Update user's password hash if the cost parameter has changed
        //$this->updateUserPasswordHash($userObject, $credential, $bcrypt);

        // Retrieve entity
        $em = $this->getServiceManager()->get('Doctrine\ORM\EntityManager');
		$accountId = $userObject->getId();
		$entity = $userObject->getAccountType()->getEntity();
		$repository = $em->getRepository('Application\Entity\\'.ucfirst($entity).'Account');
		$entityAccount = $repository->findOneByAccount($accountId);
		$container = new SessionContainer('entity');
		$container->entity = $entity;
		$container->entityAccount = $entityAccount;

        $this->setSatisfied(true);
        $storage = $this->getStorage()->read();
        $storage['identity'] = $e->getIdentity();
        $this->getStorage()->write($storage);
        $e->setCode(AuthenticationResult::SUCCESS)
          ->setMessages(array('Authentication successful.'));
    }

    protected function updateUserPasswordHash($userObject, $password, $bcrypt)
    {
        $hash = explode('$', $userObject->getPassword());
        if ($hash[2] === $bcrypt->getCost()) {
            return;
        }
        $userObject->setPassword($bcrypt->create($password));
        $this->getMapper()->update($userObject);
        return $this;
    }

    public function preprocessCredential($credential)
    {
        $processor = $this->getCredentialPreprocessor();
        if (is_callable($processor)) {
            return $processor($credential);
        }
        return $credential;
    }

    /**
     * getMapper
     *
     * @return UserMapperInterface
     */
    public function getMapper()
    {
        if (null === $this->mapper) {
            $this->mapper = $this->getServiceManager()->get('zfcuser_user_mapper');
        }
        return $this->mapper;
    }

    /**
     * setMapper
     *
     * @param UserMapperInterface $mapper
     * @return Db
     */
    public function setMapper(UserMapperInterface $mapper)
    {
        $this->mapper = $mapper;
        return $this;
    }

    /**
     * Get credentialPreprocessor.
     *
     * @return \callable
     */
    public function getCredentialPreprocessor()
    {
        return $this->credentialPreprocessor;
    }

    /**
     * Set credentialPreprocessor.
     *
     * @param $credentialPreprocessor the value to be set
     */
    public function setCredentialPreprocessor($credentialPreprocessor)
    {
        $this->credentialPreprocessor = $credentialPreprocessor;
        return $this;
    }

    /**
     * Retrieve service manager instance
     *
     * @return ServiceManager
     */
    public function getServiceManager()
    {
        return $this->serviceManager;
    }

    /**
     * Set service manager instance
     *
     * @param ServiceManager $locator
     * @return void
     */
    public function setServiceManager(ServiceManager $serviceManager)
    {
        $this->serviceManager = $serviceManager;
    }

    /**
     * @param AuthenticationOptionsInterface $options
     */
    public function setOptions(AuthenticationOptionsInterface $options)
    {
        $this->options = $options;
    }

    /**
     * @return AuthenticationOptionsInterface
     */
    public function getOptions()
    {
        if (!$this->options instanceof AuthenticationOptionsInterface) {
            $this->setOptions($this->getServiceManager()->get('zfcuser_module_options'));
        }
        return $this->options;
    }
}
