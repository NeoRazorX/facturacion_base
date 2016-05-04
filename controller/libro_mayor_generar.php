<?php


require_model('divisa.php');
require_model('partida.php');
require_model('subcuenta.php');
require_once 'plugins/facturacion_base/extras/libromayor.php';

class libro_mayor_generar extends fs_controller
{
   public $allow_delete;
   public $mostrar;   
   public $idsubcuenta;
   public $ejercicio;
   public $alias;
   public $offset;
   public $resultados; 
   public $periodo;
   public $mes;
   public $pdf_libromayor;
   public $pdf_libromayor_archivo;
   public $meses;
   public $dir_libro;
   public $cuenta;
   public $divisa; 
   public $modo;  
   
     
   
   
   

  
   
   public function __construct()
   {
      parent::__construct(__CLASS__, 'Libro_mayor', 'contabilidad', FALSE, FALSE);
   }
   
   protected function private_core()
   {
		$this->mostrar = 'generar';
		$this->modo=1;
		if( isset($_GET['modo']) )					
					if($_GET['modo']==0)
					{
					$this->modo=0;
					}
		  $subcuenta = new subcuenta();
		  $this->subcuenta = FALSE;
		  if( isset($_GET['idsubcuenta']) )
		  {
		  $this->idsubcuenta = $_GET['idsubcuenta'];
		  }
		  
		  if( isset($_GET['idsubcuenta']) )
		  {
		  
		  $this->contenido();
				if($this->subcuenta)
				{			
			/////// Llaves	 
					if( isset($_POST['puntear']) )					
					if($_POST['puntear']==1)
					{
					$this->puntear();
					}
					
					 $this->pdf_libromayor = FALSE;
	/*				 if( file_exists('tmp/'.FS_TMP_NAME.'libro_mayor/'.$this->subcuenta->idsubcuenta.'-'.$this->mes.'-'.$this->subcuenta->codejercicio.'.pdf') )
					 {
						$this->pdf_libromayor = 'tmp/'.FS_TMP_NAME.'libro_mayor/'.$this->subcuenta->idsubcuenta.'-'.$this->mes.'-'.$this->subcuenta->codejercicio.'.pdf';
					 }
	*/				 
					 $this->mes = substr($this->subcuenta->get_partidas_mes($this->offset),5,2);
					$this->meses = $this->subcuenta->meses_archivo();
					$this->pdf_libromayor_archivo =  'tmp/'.FS_TMP_NAME.'libro_mayor/'.$this->subcuenta->idsubcuenta.'-'.$this->mes.'-'.$this->subcuenta->codejercicio.'.pdf';
					$this->dir_libro = FS_TMP_NAME;
					
					if( isset($_GET['genlibro']) )
					if($_GET['genlibro']==1)
					{
					
						
						$this->contenido();
						/// generamos el PDF del libro mayor si no existe
						$libro_mayor = new libro_mayor();
						$libro_mayor->libro_mayor($this->subcuenta,$this->mes);
				//		header('Location: tmp/'.FS_TMP_NAME.'libro_mayor/'.$this->subcuenta->idsubcuenta.'.pdf');
						
/*						print '<script language="JavaScript">'; 
						print "window.open('tmp/".FS_TMP_NAME."libro_mayor/".$this->subcuenta->idsubcuenta."-".$this->mes."-".$this->subcuenta->codejercicio.".pdf','_blank' )"; 
						print '</script>';
*/						
		header('Location:index.php?page=libro_mayor_generar&idsubcuenta='.$this->idsubcuenta);				
						$this->generar_libro();
						
	
					}
					$this->contenido();
				
				}
			
		  }
	 			 if( isset($_GET['implibro']) )
				if($_GET['implibro']==1)
         		{
				$mesver = $_GET['mes'];
				$libro_mayor = new libro_mayor();
				$libro_mayor->libro_mayor_ver($this->subcuenta,$mesver);
				
				}
	  
	  
   }
   
   public function url()
   {
   					
	  if( !isset($this->subcuenta) )
      {
         return parent::url().'&'.$this->subcuenta->idcuenta;
      }
      else if($this->subcuenta)
      {
         return 'index.php?page=libro_mayor_generar&idsubcuenta='.$this->idsubcuenta;
      }
      else
         return $this->ppage->url().'&'.$this->subcuenta->idcuenta;
   }
   
      public function paginas()
   {
      $paginas = array();
      $i = 1;
      $num = 0;
      $actual = 1;
      $total = $this->subcuenta->count_partidas();
      /// añadimos todas la página
      while($num < $total)
      {
         $paginas[$i] = array(
             'url' => $this->url().'&offset='.$num,
             'num' => $i,
             'actual' => ($num == $this->offset)
         );
         if( $num == $this->offset )
            $actual = $i;
         $i++;
         $num += FS_ITEM_LIMIT;
      }
      /// ahora descartamos
      foreach($paginas as $j => $value)
      {
         if( ($j>1 AND $j<$actual-3 AND $j%10) OR ($j>$actual+3 AND $j<$i-1 AND $j%10) )
            unset($paginas[$j]);
      }
      return $paginas;
   }
   
    private function puntear()
   {      
  
      $partida = new partida();     
      foreach($this->resultados as $pa)
      {
         if( isset($_POST['punteada']) )
            $valor = in_array($pa->idpartida, $_POST['punteada']);
         else
            $valor = FALSE;
         
         if($pa->punteada != $valor)
         { 
            $pa->punteada = $valor;
			$pa->modificar();
         }
		 
      }
      $this->new_message('Datos guardados correctamente.');
   }


	private function generar_libro()
	{
		$partida = new partida();
		  foreach($this->periodo_seleccionado as $pa)
		  {
			
			$pa->libromayor = $this->mes;
			$pa->codejercicio = $this->ejercicio->codejercicio;
			$pa->modificar();
		  }
		
	}
	
	private function contenido()
	{
			 $subcuenta = new subcuenta();
      		 $this->subcuenta = FALSE;
			 $this->subcuenta = $subcuenta->get($this->idsubcuenta);
			 $this->ejercicio = $this->subcuenta->get_ejercicio();
			 
			if($this->subcuenta)
			{
			 /// configuramos la página previa
			 $this->ppage = $this->page->get('contabilidad_cuenta');
			 $this->ppage->title = 'Cuenta: '.$this->subcuenta->codcuenta;
			 $this->ppage->extra_url = '&id='.$this->subcuenta->idcuenta;
			 ////////////////////////////////
			 $this->offset = 0;
			 if( isset($_GET['offset']) )  $this->offset = intval($_GET['offset']);
						 
			 $this->resultados = $this->subcuenta->get_partidas($this->offset);
			 $this->saldo_anterior = $this->subcuenta->get_partidas_saldo_anterior();
			 $this->mes = substr($this->subcuenta->get_partidas_mes($this->offset),5,2);
			 $this->periodo = $this->subcuenta->get_nom_mes($this->mes).'  '.$this->ejercicio->codejercicio;
			
			 
			 $this->periodo_seleccionado = $this->subcuenta->get_partidas_libros($this->mes,$this->offset);
			 $this->periodo_generado = $this->subcuenta->get_partidas_libros_ver($this->mes,$this->offset);
			 
			} 
	}

   

}
