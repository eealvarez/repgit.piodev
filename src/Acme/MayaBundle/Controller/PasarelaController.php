<?php

namespace Acme\MayaBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Acme\MayaBundle\Entity\EstadoCompra;
use Acme\BackendBundle\Services\UtilService;

/**
* @Route(path="/gateway")
*/
class PasarelaController extends Controller
{
    const HMAC_SHA256 = 'sha256';
    const PAYMENT_URL = 'https://testsecureacceptance.cybersource.com/pay';
    const SECRET_KEY = '926658ca5b7046f9aba7c9cce101e295f57fa341c2ed4f749e77609be4a29f0cdc6b16ec8c9648168a086b7ba08443dc7345df08bb3d46029a4db81196e6215e21490d76d9cb48cda811792b9cdd760b42f18234713d4f5a83ecc91a221a0edc86af0f297ece4016a493140d66fe6a7daca01582cc194a28b7c4886ea4bfafbd';
    const ACCESS_KEY = 'd19fe2304b463326913d1ad2d2e6b553';
    const PROFILE_ID = '6AA1A3D4-FF29-47A3-BCDC-C61371CCBB9F';
    
    /**
     * @Route(path="/init", name="pasarela-pago-init")
    */
    public function pasarelaInitAction(Request $request) {
        
        $session = $request->getSession();
        $lastIdCompraStep1 = $session->get("last_id_compra_step1");
        if($lastIdCompraStep1 !== null && trim($lastIdCompraStep1) !== ""){
            $session->remove("last_id_compra_step1");
            $session->set("last_id_compra_step2", $lastIdCompraStep1);
            
            $init = $request->query->get('i');
            if (is_null($init)) {
                $init = $request->request->get('i');
            }
            
            $compra = $this->getDoctrine()->getRepository('MayaBundle:Compra')->find($lastIdCompraStep1);
            if($compra === null){
                $this->get("logger")->error("No se pudo encontro la compra en la db con el last_id: ".$lastIdCompraStep1); 
                return $this->redirect($this->generateUrl('ticket-full'), 301);
            }
        
            if($init === true || $init === "true"){
                
                $params = array(
                    'access_key' => self::ACCESS_KEY,
                    'profile_id' => self::PROFILE_ID,
                    'transaction_uuid' => $compra->getTransactionUuid(),
                    'signed_date_time' => gmdate('Y-m-d\TH:i:s\Z'),
                    'locale' => 'es-us',
                    'transaction_type' => 'sale',
                    'reference_number' => $compra->getHashCode(),
                    'amount' => $compra->getPrecio(),
                    'currency' => 'GTQ',
                    'bill_to_email' => $compra->getCliente()->getCorreo(),
                    'signed_field_names' => 'access_key,profile_id,transaction_uuid,signed_field_names,unsigned_field_names,signed_date_time,locale,transaction_type,bill_to_email,reference_number,amount,currency',
                    'unsigned_field_names' => ''
                );
                
                $signature = $this->sign($params);
                
                return $this->render('MayaBundle:Pasarela:submitdata.html.twig', array(
                    'payment_url' => self::PAYMENT_URL,
                    'data' => $params,
                    'signature' => $signature
                ));
            }
        }
        
        return $this->redirect($this->generateUrl('ticket-full'), 301);
    }
    
    /**
     * @Route(path="/result", name="pasarela-pago-receipt")
    */
    public function resultAction(Request $request) {
        
        $idLastCompra = "";
        $params = array();
        foreach($_REQUEST as $name => $value) {
            $params[$name] = $value;
        }
        
//        $session = $request->getSession();
//        $idLastCompra = $session->get("last_id_compra_step2");
//        if($idLastCompra === null || trim($idLastCompra) === ""){
//            $this->get("logger")->error("No se pudo encontro la compra en la session. Valores: " . implode(";", $params)); 
//            return $this->redirect($this->generateUrl('ticket-full'), 301);
//        }
//        $session->remove("last_id_compra_step2");
        
        if(strcmp($params["signature"], $this->sign($params)) != 0){
            //datos corruptos
            $this->get("logger")->error("Datos corruptos. Valores: " . implode(";", $params)); 
            return $this->redirect($this->generateUrl('ticket-full'), 301);
        }
        
        if(!isset($params["req_reference_number"]) || $params["req_reference_number"] === null || trim($params["req_reference_number"]) === ""){
            $this->get("logger")->error("Datos corruptos. No se detecto el valor de req_reference_number. Valores: " . implode(";", $params)); 
            return $this->redirect($this->generateUrl('ticket-full'), 301);
        }
        
        if(!isset($params["req_transaction_uuid"]) || $params["req_transaction_uuid"] === null || trim($params["req_transaction_uuid"]) === ""){
            $this->get("logger")->error("Datos corruptos. No se detecto el valor de req_transaction_uuid. Valores: " . implode(";", $params)); 
            return $this->redirect($this->generateUrl('ticket-full'), 301);
        }
        
        if(!isset($params["transaction_id"]) || $params["transaction_id"] === null || trim($params["transaction_id"]) === ""){
            $this->get("logger")->error("Datos corruptos. No se detecto el valor de transaction_id. Valores: " . implode(";", $params)); 
            return $this->redirect($this->generateUrl('ticket-full'), 301);
        }
        
        if(strcmp(strtolower($params["decision"]), "accept") != 0){
            $message = $params["message"];
            $this->get("logger")->error("La decision de la compra fue: ".$params["decision"]. ". Message: " . $message. ". Valores: " . implode(";", $params)); 
//            $session->remove("last_id_compra_step2");
            return $this->render("MayaBundle:Pasarela:result.html.twig", array(
                'title' => 'La compra no pudo ser procesada.',
                'message' => $message,
                'idCompra' => "",
            ));
        }
        
        $hashCode = $params["req_reference_number"];
        $compra = $this->getDoctrine()->getRepository('MayaBundle:Compra')->findOneByHashCode($hashCode);
        if($compra === null){
            $this->get("logger")->error("No se pudo encontro la compra en la db con el hashcode: ".$hashCode.". Valores: " . implode(";", $params)); 
            return $this->redirect($this->generateUrl('ticket-full'), 301);
        }
        $idLastCompra = $compra->getId();
        
        if(floatval($compra->getPrecio()) !=  floatval($params["req_amount"])){
            $this->get("logger")->error("Los precios no coinciden: Precio Compra: ". $compra->getPrecio() ." vs REQ_AMOUNT: " . $params["req_amount"] . ". Valores: " . implode(";", $params)); 
            return $this->redirect($this->generateUrl('ticket-full'), 301);
        }
        
        if($compra->getTransactionUuid() !=  $params["req_transaction_uuid"]){
            $this->get("logger")->error("La req_transaction_uuid no coincide: Real: ". $compra->getTransactionUuid() ." vs Recibida: " . $params["req_transaction_uuid"] . ". Valores: " . implode(";", $params)); 
            return $this->redirect($this->generateUrl('ticket-full'), 301);
        }
        
        if($compra !== null){
            $em = $this->getDoctrine()->getManager();
            $em->getConnection()->beginTransaction();
            try {
                $compra->setMetodoPago("Cybersource");
                $compra->setReferenciaPago($params["transaction_id"]);
                $compra->setEstado($this->getDoctrine()->getRepository('MayaBundle:EstadoCompra')->find(EstadoCompra::PAGADA));
                $em->persist($compra);
                $em->flush();
                $em->getConnection()->commit();
            } catch (\RuntimeException $exc) {
                $em->getConnection()->rollback();
                $this->get("logger")->error("ERROR:" . $exc->getMessage());  
                $compra = null;
            } catch (\ErrorException $exc) {
                $em->getConnection()->rollback();
                $this->get("logger")->error("ERROR:" . $exc->getMessage());  
                $compra = null;
            } catch (\Exception $exc) {
                $em->getConnection()->rollback();
                $this->get("logger")->error("ERROR:" . $exc->getMessage());  
                $compra = null;
            }
        }
        
        if($compra !== null){
            
            $em->getConnection()->beginTransaction();
            try {
            
                $pasajeros = $compra->getListaPasajeros();
                $boletosArray = array();
                foreach ($pasajeros as $pasajero) {
                    $paquetes = $pasajero->getListaPaquetes();
                    foreach ($paquetes as $paquete) {
                        $boletos = $paquete->getListaBoletos();
                        foreach ($boletos as $boleto) {
                            $fullname = $pasajero->getNombreApellidos();
                            $nombres = explode(" ", $fullname);
                            $primerNombre = $nombres[0];
                            $segundoNombre = "";
                            $primerApellido = "";
                            $segundoApellido = "";
                            if(count($nombres) === 2){
                                $primerApellido = $nombres[1];
                            }
                            else if(count($nombres) === 3){
                                $primerApellido = $nombres[1];
                                $segundoApellido = $nombres[2];
                            }
                            else if(count($nombres) > 3){
                                $segundoNombre = $nombres[1];
                                $primerApellido = $nombres[2];
                                $segundoApellido = implode(array_slice($nombres, 3));
                            }
                            $boletosArray [] = array(
                                'idReservacion' => $boleto->getIdExternoReservacion(),
                                'idSubeEn' => $boleto->getSubeEn()->getId(),
                                'idBajaEn' => $boleto->getBajaEn()->getId(),
                                'idTarifa' => $boleto->getTarifa()->getId(),
                                'precioBase' => $boleto->getPrecio(),
                                'nacionalidad' => $pasajero->getNacionalidad()->getId(),
                                'tipoDocumento' => $pasajero->getTipoDocumento()->getId(),
                                'numeroDocumento' => $pasajero->getValorDocumento(),
                                'primerNombre' => $primerNombre,
                                'segundoNombre' => $segundoNombre,
                                'primerApellido' => $primerApellido,
                                'segundoApellido' => $segundoApellido,
                                'sexo' => $pasajero->getSexo() === null ? "" : $pasajero->getSexo()->getId(),
                                'fechaNacimiento' => $pasajero->getFechaNacimiento() === null ? "" : $pasajero->getFechaNacimiento()->getId(),
                                'fechaVencimientoDocumento' => $pasajero->getFechaVencimientoDocumento() === null ? "" : $pasajero->getFechaVencimientoDocumento()->getId(),
                                'detallado' => $pasajero->getDetallado()
                                
                            );
                        }
                    }
                }
                
                if(count($boletosArray) === 0){
                    throw new \RuntimeException("ERROR. La compra ID: ".$idLastCompra." no tiene boletos.");
                }
                
                $listBoletos = $this->generarBoletosSistemaInterno($idLastCompra, $boletosArray);
                foreach ($listBoletos as $item) {
                    $em->persist($item);
                }
                
                $em->flush();
                $em->getConnection()->commit();
                
                return $this->render("MayaBundle:Pasarela:result.html.twig", array(
                    'title' => 'Compra realizada satisfactoriamente.',
                    'idCompra' => $idLastCompra,
                    'compra' => $compra
                ));
                
            } catch (\RuntimeException $exc) {
                $em->getConnection()->rollback();
                $this->get("logger")->error("ERROR-R1:" . $exc->getMessage()); 
            } catch (\ErrorException $exc) {
                $em->getConnection()->rollback();
                $this->get("logger")->error("ERROR-R2:" . $exc->getTraceAsString());
            } catch (\Exception $exc) {
                $em->getConnection()->rollback();
                $this->get("logger")->error("ERROR-R3:" . $exc->getTraceAsString());
            }
        }
        
        return $this->pasarelaSuccessFailed($idLastCompra, $compra);
    }
    
    private function generarBoletosSistemaInterno($idLastCompra, $boletosArray, $intento = 1) {
        $this->get("logger")->error("GENERANDO BOLETOS EN EL SISTEMA INTERNO. INTENTO NRO: ". $intento); 
        $boletos = array();
        try {
            
            $idApp = $this->container->getParameter("id_empresa_app");
            $now = new \DateTime();
            $dataWeb = $now->format('Y-m-d'). "_system_web_".$idApp;
            $claveInterna = $this->container->getParameter("internal_sys_clave_interna");
            $tokenAutLocal = UtilService::encrypt($claveInterna, $dataWeb);

            $dataBoletos = array( "idWeb" => $idApp, "tokenAut" => $tokenAutLocal, "data" => json_encode($boletosArray));
            $postdata = http_build_query($dataBoletos);
            $options = array(
                  'http' => array(
                  'method'  => 'POST',
                  'content' => $postdata,
                  'header'  => "Content-type: application/x-www-form-urlencoded\r\n"));
            $context  = stream_context_create( $options );
            $url =  $this->container->getParameter("internal_sys_url") .
                    $this->container->getParameter("internal_sys_pref") .
                    "cb.json";
            $resultHtml = file_get_contents($url, false, $context);
            if($resultHtml === null){
                throw new \RuntimeException("ERROR. CREANDO BOLETO EN EL SISTEMA INTERNO.  DATA: NULL");
            }
            if(trim($resultHtml) === ""){
                throw new \RuntimeException("ERROR. CREANDO BOLETO EN EL SISTEMA INTERNO.  DATA: " . $resultHtml);
            }    
            $result = json_decode($resultHtml);
            if(!isset($result)){
                throw new \RuntimeException("ERROR. CREANDO BOLETO EN EL SISTEMA INTERNO. JSON DECODE FAIL.  DATA: " . $resultHtml);
            }
            if(!isset($result->data)){
                throw new \RuntimeException("ERROR. CREANDO BOLETO EN EL SISTEMA INTERNO. NO SE DETECTO EL TAG DATA EN EL JSON.  DATA: " . $resultHtml);
            }
            if($result->data === null || $result->data === ""){
                throw new \RuntimeException("ERROR. CREANDO BOLETO EN EL SISTEMA INTERNO. TAG DATA VACIO.  DATA: " . $resultHtml);
            }
            if(!(is_array($result->data) || $result->data instanceof \Traversable)){
                throw new \RuntimeException("ERROR. CREANDO BOLETO EN EL SISTEMA INTERNO. TAG DATA NO ES ITERABLE. DATA: " . $resultHtml);
            }
            
            $this->get("logger")->error("BOLETOS GENERADOS SATISFACTORIAMENTE. INTENTO NRO: ". $intento);
            foreach ($result->data as $boletoReservacion) {
                $boletoParaActualizar = $this->getDoctrine()->getRepository('MayaBundle:Boleto')->findOneByIdExternoReservacion($boletoReservacion->idReservacion);
                $boletoParaActualizar->setIdExternoBoleto($boletoReservacion->idBoleto);
                $boletos[] = $boletoParaActualizar;
            }
            $this->get("logger")->error("EXITEN UN TOTAL DE " .  count($boletos)." BOLETOS PARA ACTUALIZAR. INTENTO NRO: ". $intento);
            return $boletos;
            
        } catch (\RuntimeException $exc) {
            $this->get("logger")->error("ID COMPRA: ".$idLastCompra.", INTENTO: " . $intento .". ERROR:" . $exc->getMessage()); 
            $intento++;
            if($intento > 4){
                throw new \RuntimeException("ID COMPRA: ".$idLastCompra. ". ERROR. Superado el maximo numero de intentos para crear los boletos internos.");
            }else{
                return $this->generarBoletosSistemaInterno($idLastCompra, $boletosArray, $intento);
            }
        } catch (\ErrorException $exc) {
            $this->get("logger")->error("ID COMPRA: ".$idLastCompra.", INTENTO: " . $intento .". ERROR:" . $exc->getMessage()); 
            $intento++;
            if($intento > 4){
                throw new \RuntimeException("ID COMPRA: ".$idLastCompra. ". ERROR. Superado el maximo numero de intentos para crear los boletos internos.");
            }else{
                return $this->generarBoletosSistemaInterno($idLastCompra, $boletosArray, $intento);
            }
        } catch (\Exception $exc) {
            $this->get("logger")->error("ID COMPRA: ".$idLastCompra.", INTENTO: " . $intento .". ERROR:" . $exc->getMessage());
            $intento++;
            if($intento > 4){
                throw new \RuntimeException("ID COMPRA: ".$idLastCompra. ". ERROR. Superado el maximo numero de intentos para crear los boletos internos.");
            }else{
                return $this->generarBoletosSistemaInterno($idLastCompra, $boletosArray, $intento);
            }
        }
    }
        
    private function pasarelaSuccessFailed($idLastCompra, $compra = null) {
        return $this->render("MayaBundle:Pasarela:result.html.twig", array(
            'title' => 'Lo sentimos. Ocurrio un error mientras procesamos su pedido.',
            'idCompra' => $idLastCompra,
            'compra' => $compra
        ));
    }
    
    /**
     * @Route(path="/cancel", name="pasarela-pago-cancel")
    */
    public function cancelAction(Request $request) {
        $session = $request->getSession();
        $idLastCompra = $session->get("last_id_compra_step2");
        $session->remove("last_id_compra_step2");
        $this->get("logger")->error("La compra fue CANCELADA."); 
         
        $em = $this->getDoctrine()->getManager();
        $em->getConnection()->beginTransaction();
        $compra = null;
        try {
            if($idLastCompra !== null && trim($idLastCompra) !== ""){
                $compra = $this->getDoctrine()->getRepository('MayaBundle:Compra')->find($idLastCompra);
                if($compra !== null){
                    $compra->setEstado($this->getDoctrine()->getRepository('MayaBundle:EstadoCompra')->find(EstadoCompra::CANCELADA));
                    $em->persist($compra);
                    $em->flush();
                    $em->getConnection()->commit();
                }
            }
        
        } catch (\RuntimeException $exc) {
            $em->getConnection()->rollback();
            $message = $exc->getMessage();
            var_dump($message);
            $this->get("logger")->error("ERROR:" . $message);    
        } catch (\Exception $exc) {
            $em->getConnection()->rollback();
            $message = $exc->getMessage();
            var_dump($message);
            $this->get("logger")->error("ERROR:" . $message);
        } 
        
//        return $this->redirect($this->generateUrl('_maya_homepage'), 301);
        
        return $this->render("MayaBundle:Pasarela:result.html.twig", array(
            'title' => 'Transacción Cancelada.',
            'idCompra' => $idLastCompra,
            'compra' => $compra
        ));
    }
    
//    /**
//     * @Route(path="/page.html", name="pasarela-pago-page")
//    */
//    public function basePageAction(Request $request) {
//        $success = $request->query->get('success');
//        if (is_null($success)) {
//            $success = $request->request->get('success');
//        }
//        $failed = $request->query->get('failed');
//        if (is_null($failed)) {
//            $failed = $request->request->get('failed');
//        }
//        return $this->render("MayaBundle:Pasarela:pasarela.html.twig", array(
//            'success' => $success,
//            'failed' => $failed
//        ));
//    }
    
    public function sign($params) {
        return $this->signData($this->buildDataToSign($params), self::SECRET_KEY);
    }

    public function signData($data, $secretKey) {
        return base64_encode(hash_hmac('sha256', $data, $secretKey, true));
    }

    public function buildDataToSign($params) {
        $signedFieldNames = explode(",",$params["signed_field_names"]);
        foreach ($signedFieldNames as $field) {
           $dataToSign[] = $field . "=" . $params[$field];
        }
        return $this->commaSeparate($dataToSign);  
    }

    public function commaSeparate($dataToSign) {
        return implode(",",$dataToSign);
    }
    
    
    
}