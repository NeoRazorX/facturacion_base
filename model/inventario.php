<?php


class inventario extends fs_model
{
   public $idinventario;
   public $referencia;
   public $fecha_ingreso;
   public $cantidad;
   public $preciocoste;
   public $borrado;
   public $codalmacen;
   
   
   public function __construct($s=FALSE)
   {
      parent::__construct('inventario', 'plugins/facturacion_base/');
      if($s)
      {
         $this->idinventario = $this->intval($s['idinventario']);
         $this->referencia = $s['referencia'];
         $this->fecha_ingreso = Date('d-m-Y', strtotime($s['fecha_ingreso']));
         $this->cantidad = floatval($s['cantidad']);
         $this->preciocoste = floatval($s['preciocoste']);
         $this->borrado = $this->intval($s['borrado']);
		 $this->codalmacen = $s['codalmacen'];
      }
      else
      {
         $this->idinventario = NULL;
         $this->referencia = NULL;
         $this->fecha_ingreso = Date('d-m-Y');
         $this->cantidad = 0;
         $this->preciocoste = 0;
         $this->borrado = 0;
		 $this->codalmacen = NULL;
      }
   }
   
   protected function install()
   {
      return '';
   }
   

   
   public function get($id)
   {
      $inventario = $this->db->select("SELECT * FROM ".$this->table_name." WHERE idinventario = ".$this->var2str($id).";");
      if($inventario)
      {
         return new inventario($inventario[0]);
      }
      else
         return FALSE;
   }
   
      public function get_ref($ref)
   {
      $inventario = $this->db->select("SELECT * FROM ".$this->table_name." WHERE referencia = ".$this->var2str($ref).";");
      if($inventario)
      {
         return new inventario($inventario[0]);
      }
      else
         return FALSE;
   }
   

   
   public function exists()
   {

         $valor = $this->db->select("SELECT * FROM ".$this->table_name." WHERE referencia = ".$this->var2str($this->referencia)." and preciocoste =".$this->var2str($this->preciocoste).";");
		 if($valor) return TRUE;
		 else return FALSE;
		 
   }
   
   public function save()
   {
      if( $this->exists() )
      {
         $sql = "UPDATE ".$this->table_name." SET fecha_ingreso = ".$this->var2str($this->fecha_ingreso).", 
			cantidad = ".$this->var2str($this->cantidad).", 
			codalmacen = ".$this->var2str($this->codalmacen).", borrado = ".$this->var2str($this->borrado)."
            WHERE referencia = ".$this->var2str($this->referencia)." and preciocoste = ".$this->var2str($this->preciocoste).";";
         
         return $this->db->exec($sql);
      }
      else
      {
         $sql = "INSERT INTO ".$this->table_name." (referencia,fecha_ingreso,cantidad,preciocoste,codalmacen,
		 borrado) VALUES (".$this->var2str($this->referencia).",".$this->var2str($this->fecha_ingreso).",
            ".$this->var2str($this->cantidad).",".$this->var2str($this->preciocoste).",
			".$this->var2str($this->codalmacen).",".$this->var2str($this->borrado).");";
         
         if( $this->db->exec($sql) )
         {
            $this->idinventario = $this->db->lastval();
            return TRUE;
         }
         else
            return FALSE;
      }
   }
   
   public function delete()
   {
      return $this->db->exec("DELETE FROM ".$this->table_name." WHERE idinventario = ".$this->var2str($this->idinventario).";");
   }
   
   public function guardar_cantidad()
   {
            $sql = "UPDATE ".$this->table_name." SET cantidad = ".$this->var2str($this->cantidad).", 
			codalmacen = ".$this->var2str($this->codalmacen).", borrado = ".$this->var2str($this->borrado)."
            WHERE idinventario = ".$this->var2str($this->idinventario)." ;";
         
         return $this->db->exec($sql);
   }
   
      public function guardar_modif_refe($nreferencia)
   {
            $sql = "UPDATE ".$this->table_name." SET referencia = ".$this->var2str($nreferencia)."
            WHERE referencia = ".$this->var2str($this->referencia)." ;";
         
         return $this->db->exec($sql);
   }
   
   public function inventario_agregar( $codalmacen,$referencia,$cantidad,$pvpunitario)
   {
   		$this->referencia = $referencia;
   		// Toma del Stock negativo si quedó
		$cantidad_negativa = $this->buscar_referencia_stock_negativo();

   		if( $cantidad_negativa ) 
		{

			$cantidad = $cantidad + $cantidad_negativa->cantidad;
			$this->borra_reg_negativo();
		}
			

//         $this->referencia = $referencia;
         $this->cantidad = $cantidad;
         $this->preciocoste = $pvpunitario;
		 $this->codalmacen = $codalmacen;
/*		 print '<script language="JavaScript">'; 
				print 'alert(" id partida '.$GLOBALS['config2']['stock_negativo'].' ");'; 
				print '</script>';
*/
		
		 if($cantidad >= 0)
		 {
		 	
		 
			$canti = $this->buscar_referencia_precio();		 
			if( $canti )
			{
			$c = $this->get($canti->idinventario);	

			 $this->cantidad = $this->cantidad + $c->cantidad;
			} 
			// Si quedó una cuenta en cero la elimina
			$this->delete_ceros();
			
			$this->save();
		 }
		  else if($cantidad < 0)
		 {
				$canti = $this->buscar_referencia_antiguo();
				if( $canti )
				{
						$cantiVal = $canti->cantidad + $cantidad;
						
						if($cantiVal >= 0 )
						{
							$this->idinventario = $canti->idinventario;
							$this->cantidad = $cantiVal;
							$this->guardar_cantidad();
						}
						while ($cantiVal <= 0)
						{
								$this->idinventario = $canti->idinventario;
								$this->cantidad = 0;
							//	$this->guardar_cantidad();
								$this->delete();
								$canti = $this->buscar_referencia_antiguo();
								if( $canti )
								{
										$cantiVal = $canti->cantidad + $cantiVal;
										
										if( $cantiVal >= 0 )
										{
											$this->idinventario = $canti->idinventario;
											$this->cantidad = $cantiVal;
											$this->guardar_cantidad();
										}
								}
								else
								{
								
									$this->cantidad = $cantiVal;
									$cantiVal = 1; // Para salir del lazo
									$this->save();
								}
								
							
						
						}
				}
				else
				{
					$this->save();
				}
		
			}
			else return FALSE;
 
   		
   
   }
   
   
   
   public function buscar_referencia_precio()
   {
   $existe_inv = $this->db->select("SELECT * FROM ".$this->table_name." WHERE referencia = ".$this->var2str($this->referencia)." and preciocoste =".$this->var2str($this->preciocoste).";");
         if($existe_inv)
      {
         return new inventario($existe_inv[0]);
      }
      else
         return FALSE;
   }
   
      public function buscar_referencia_antiguo()
   {
   $existe_inv = $this->db->select("SELECT * FROM ".$this->table_name." WHERE referencia = ".$this->var2str($this->referencia)." and cantidad > 0 order by fecha_ingreso, idinventario asc;");
       if($existe_inv)
      {
         return new inventario($existe_inv[0]);
      }
      else
         return FALSE;
   }
   
         public function buscar_referencia_stock_negativo()
   {
   $existe_inv = $this->db->select("SELECT * FROM ".$this->table_name." WHERE referencia = ".$this->var2str($this->referencia)." and cantidad < 0 order by fecha_ingreso, idinventario asc;");
       if($existe_inv)
      {
         return new inventario($existe_inv[0]);
      }
      else
         return FALSE;
   }
   
      public function borra_reg_negativo()
   {
   		return $this->db->exec("DELETE FROM ".$this->table_name." WHERE cantidad < 0 ;");
  
   }
   
   public function delete_ceros()
   {
   		return $this->db->exec("DELETE FROM ".$this->table_name." WHERE cantidad = 0 ;");
  
   }
   
}
