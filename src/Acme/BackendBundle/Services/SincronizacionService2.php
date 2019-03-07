<?php

namespace Acme\BackendBundle\Services;

use Acme\BackendBundle\Scheduled\ScheduledServiceInterface;

class SincronizacionService2 extends SincronizacionService implements ScheduledServiceInterface{
    
    protected $container;
    protected $doctrine;
    protected $options;
    protected $logger;
    
    public function __construct($container) { 
        $this->container = $container;
        $this->doctrine = $this->container->get('doctrine');
        $this->logger = $this->container->get('logger');
    }
    
    public function setScheduledJob(\Acme\BackendBundle\Entity\Job $job = null) {
        $this->logger->warn("SincronizacionService2: SincronizacionService-init");
        $this->job = $job;
        try {
            $this->getActualizacionesAsientosOcupadosBySalidas();
        } catch (\Exception $ex) {
            $this->logger->warn("Ocurrio una exception en el proceso de getActualizacionesAsientosOcupadosBySalidas");
            throw $ex;
        }
        $this->logger->warn("SincronizacionService2: SincronizacionService-end");
    }
    
    public function getActualizacionesAsientosOcupadosBySalidas(){
        $this->logger->warn("SincronizacionService2: getActualizacionesAsientosOcupadosBySalidas - init"); 
        $url = $this->container->getParameter("internal_sys_url") .
               $this->container->getParameter("internal_sys_pref") .
               "ao.json";
        
        $result =  $this->doRequestIntegration($url);
        $response = json_decode($result, true);
        $items = $response['data'];
        
        $em = $this->doctrine->getManager();
        $em->getConnection()->beginTransaction();
                
        try {
            
            foreach ($items as $idConexionSimple => $cantidad) {
                var_dump("SincronizacionService2: Salida: ".$idConexionSimple. " => " .$cantidad);
                $conexionSimple = $this->doctrine->getRepository('MayaBundle:ConexionSimple')->findOneByIdExterno($idConexionSimple);
                if($conexionSimple !== null){
                    $conexionSimple->setCantVendidos($cantidad);
                    $em->persist($conexionSimple);
                }
            }
            $em->flush();
            $em->getConnection()->commit();
           
        } catch (\RuntimeException $exc) {
            $em->getConnection()->rollback();
//            var_dump("ERROR:" . $exc->getMessage());
            $this->logger->error("ERROR:" . $exc->getMessage());
        } catch (\ErrorException $exc) {
            $em->getConnection()->rollback();
//            var_dump("ERROR:" . $exc->getMessage());
            $this->logger->error("ERROR:" . $exc->getMessage());
            
        } catch (\Exception $exc) {
            $em->getConnection()->rollback();
//            var_dump("ERROR:" . $exc->getMessage());
            $this->logger->error("ERROR:" . $exc->getMessage());
        }
        
        $this->logger->warn("SincronizacionService2: getActualizacionesAsientosOcupadosBySalidas - end");
    }
}
