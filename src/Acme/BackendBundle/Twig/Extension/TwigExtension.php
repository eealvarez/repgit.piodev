<?php

namespace Acme\BackendBundle\Twig\Extension;

use \Twig_Extension;
use \Twig_Function_Method;
use \Symfony\Component\DependencyInjection\ContainerInterface;

class TwigExtension extends Twig_Extension {
    
    protected $container;
    
    function __construct(ContainerInterface $container) {
        $this->container = $container;
    }
    
    public function getName() {
        return 'twigExtension';
    }    
    
    public function getFunctions() {
        return array(
            new \Twig_SimpleFunction('getGalerias', array($this, 'getGalerias')),
            new \Twig_SimpleFunction('getGaleriaById', array($this, 'getGaleriaById')),
            new \Twig_SimpleFunction('getGaleriaByReferencia', array($this, 'getGaleriaByReferencia')),
            new \Twig_SimpleFunction('getImagenesGaleria', array($this, 'getImagenesGaleria')),
            new \Twig_SimpleFunction('getEstacionesByIds', array($this, 'getEstacionesByIds')),
            new \Twig_SimpleFunction('getAllEstacionesPrincipales', array($this, 'getAllEstacionesPrincipales')),
            new \Twig_SimpleFunction('getOficinasActivasByDepartamento', array($this, 'getOficinasActivasByDepartamento')),
            new \Twig_SimpleFunction('getIsUserOauth', array($this, 'getIsUserOauth')),
            new \Twig_SimpleFunction('getCodigoClienteOauth', array($this, 'getCodigoClienteOauth')),
            new \Twig_SimpleFunction('getMapGuatemala', array($this, 'getMapGuatemala')),
            new \Twig_SimpleFunction('getParameter', array($this, 'getParameter'))
        );        
    }
    /*
        A través de la variable $entorno puedes acceder a información como la versión de Twig
        ($entorno::VERSION), la codificación de caracteres utilizada ($entorno->getCharset()), o si
        Twig se está ejecutando en modo debug ($entorno->isDebug()).
     */
    public function getFilters()
    {
        /*
         * FILTRO DINAMICO. MACHEA CON mostrar_ul, mostrar_ol, mostrar_xx
         */
        return array();
    }
    
    public function getMapGuatemala()
    {        
        $longitude = 14.654162;
        $latitude = -90.551068;
        $map = $this->container->get('ivory_google_map.map');
        $marker = $this->container->get('ivory_google_map.marker');
        $marker->setPosition($longitude, $latitude, true);
        $marker->setAnimation(\Ivory\GoogleMap\Overlays\Animation::BOUNCE);
        $map->addMarker($marker);
        $map->setMapOption('zoom', 6);
        $map->setStylesheetOption('width', '100%');
        $map->setStylesheetOption('height', '450px');
        $map->setZoomControl(\Ivory\GoogleMap\Controls\ControlPosition::TOP_LEFT, \Ivory\GoogleMap\Controls\ZoomControlStyle::DEFAULT_);
        $map->setCenter($longitude, $latitude, true);
        return $this->container->get('templating')->render("MayaBundle:Map:mapa.html.twig" , array(
            "map" => $map
        ));
    }
    
    public function getCodigoClienteOauth($user){
        $emp = $this->container->getParameter("id_empresa_app");
        return $user->getCodigo($emp);
    }
    
    public function getParameter($clave)
    {        
       return $this->container->getParameter($clave);         
    }
    
    public function getIsUserOauth($user) {
        return ($user instanceof \Acme\BackendBundle\Entity\UserOauth);
    }
    
    public function getOficinasActivasByDepartamento(){
        $mapValues = array();
        $items = $this->container->get('doctrine')->getRepository('MayaBundle:Estacion')->getOficinasActivasByDepartamento();
        foreach ($items as $estacion) {
            $departamento = $estacion->getDepartamento();
            if($departamento !== null){
                $clave = strtoupper($departamento->getNombre());
                if(!isset($mapValues[$clave])){
                    $mapValues[$clave] = array();
                }
                $mapValues[$clave][] = $estacion;
            }
        }
        return $mapValues;
    }
    
    public function getEstacionesByIds($ids) {
        $items = $this->container->get('doctrine')->getRepository('MayaBundle:Estacion')->findBy(array('id' => $ids));
        return $items;
    }
    
    public function getAllEstacionesPrincipales() {
        $items = $this->container->get('doctrine')->getRepository('MayaBundle:Estacion')->getAllEstacionesPrincipales();
        return $items;
    }
    
    public function getImagenesGaleria($id) {
        $items = $this->container->get('doctrine')->getRepository('MayaBundle:Imagen')->listarImagenesGaleria($id);
        return $items;
    }
    
    public function getGaleriaById($id) {
        $item = $this->container->get('doctrine')->getRepository('MayaBundle:Galeria')->find($id);
        return $item;
    }
    
    public function getGaleriaByReferencia($referencia) {
        $items = $this->container->get('doctrine')->getRepository('MayaBundle:Galeria')->listarGaleriaByReferencia($referencia);
        return $items;
    }
    
    public function getGalerias() {
        $items = $this->container->get('doctrine')->getRepository('MayaBundle:Galeria')->listarGaleriaActivas();
        return $items;
    }
    
    protected function getRootDir()
    {
        return __DIR__.'\\..\\..\\..\\..\\..\\web\\';
    }
}

?>
