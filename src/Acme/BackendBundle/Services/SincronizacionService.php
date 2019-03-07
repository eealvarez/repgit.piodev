<?php
namespace Acme\BackendBundle\Services;

use Acme\BackendBundle\Scheduled\ScheduledServiceInterface;

abstract class SincronizacionService implements ScheduledServiceInterface{
    
    protected $container;
    protected $doctrine;
    protected $options;
    protected $logger;
    
    public function __construct($container) { 
        $this->container = $container;
        $this->doctrine = $this->container->get('doctrine');
        $this->logger = $this->container->get('logger');
    }
    
    public function doRequestIntegration($url, $arrayData = null){
        
        $idApp = $this->container->getParameter("id_empresa_app");
        $now = new \DateTime();
        $dataWeb = $now->format('Y-m-d'). "_system_web_".$idApp;
        $claveInterna = $this->container->getParameter("internal_sys_clave_interna");
        $tokenAutLocal = \Acme\BackendBundle\Services\UtilService::encrypt($claveInterna, $dataWeb);
        
        $data = array( "idWeb" => $idApp, "tokenAut" => $tokenAutLocal );
        if($arrayData !== null){
            $data['data'] = json_encode($arrayData);
        }
            
        $postdata = http_build_query($data);
        $options = array(
              'http' => array(
              'method'  => 'POST',
              'content' => $postdata,
              'header'  => "Content-type: application/x-www-form-urlencoded\r\n"));
        $context  = stream_context_create( $options );
        $result = file_get_contents($url, false, $context );
        return $result;
    }
    
    public function isNewId($id)
    {
        return ($id === null || trim($id) === "" || trim($id) === "0");
    }
}
