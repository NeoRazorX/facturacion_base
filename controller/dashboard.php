<?php

/*
 * @author Carlos García Gómez      neorazorx@gmail.com
 * @copyright 2016, Carlos García Gómez. All Rights Reserved.
 */

/**
 * Description of dashboard
 *
 * @author carlos
 */
class dashboard extends fs_controller
{
   public $anyo;
   public $mes;
   public $noticias;
   public $trimestre;
   
   public function __construct()
   {
      parent::__construct(__CLASS__, 'Dashboard', 'informes', FALSE, TRUE, TRUE);
   }
   
   protected function private_core()
   {
      $this->mes = array(
          'desde' => date('1-m-Y'),
          'hasta' => date('d-m-Y'),
          'anterior' => '-1 month',
          'compras' => 0,
          'compras_anterior' => 0,
          'compras_mejora' => 0,
          'compras_albaranes_pte' => 0,
          'compras_pedidos_pte' => 0,
          'compras_sinpagar' => 0,
          'ventas' => 0,
          'ventas_anterior' => 0,
          'ventas_mejora' => 0,
          'ventas_albaranes_pte' => 0,
          'ventas_pedidos_pte' => 0,
          'ventas_sinpagar' => 0,
          'impuestos' => 0,
          'impuestos_anterior' => 0,
          'impuestos_mejora' => 0,
          'beneficios' => 0,
          'beneficios_anterior' => 0,
          'beneficios_mejora' => 0,
      );
      if( intval( date('d') ) <= 7 )
      {
         /// en los primeros días del mes, mejor comparamos los datos del anterior
         $this->mes['desde'] = date('1-m-Y', strtotime('-1 month'));
         $this->mes['hasta'] = date('t-m-Y', strtotime('-1 month'));
      }
      $this->calcula_periodo($this->mes, TRUE);
      
      $this->trimestre = array(
          'anterior' => '-3 months',
          'compras' => 0,
          'compras_anterior' => 0,
          'compras_mejora' => 0,
          'ventas' => 0,
          'ventas_anterior' => 0,
          'ventas_mejora' => 0,
          'impuestos' => 0,
          'impuestos_anterior' => 0,
          'impuestos_mejora' => 0,
          'beneficios' => 0,
          'beneficios_anterior' => 0,
          'beneficios_mejora' => 0,
      );
      
      /// ahora hay que calcular bien las fechas del trimestre
      switch( date('m') )
      {
         case '1':
            $this->trimestre['desde'] = date('01-10-Y', strtotime('-1 year'));
            $this->trimestre['hasta'] = date('t-12-Y', strtotime('-1 year'));
            break;
         
         case '2':
         case '3':
         case '4':
            $this->trimestre['desde'] = date('01-01-Y');
            $this->trimestre['hasta'] = date('t-03-Y');
            break;
         
         case '5':
         case '6':
         case '7':
            $this->trimestre['desde'] = date('01-04-Y');
            $this->trimestre['hasta'] = date('t-06-Y');
            break;
         
         case '8':
         case '9':
         case '10':
            $this->trimestre['desde'] = date('01-07-Y');
            $this->trimestre['hasta'] = date('t-09-Y');
            break;
         
         case '11':
         case '12':
            $this->trimestre['desde'] = date('1-10-Y');
            $this->trimestre['hasta'] = date('t-12-Y');
            break;
      }
      $this->calcula_periodo($this->trimestre);
      
      $this->anyo = array(
          'desde' => date('01-01-Y'),
          'hasta' => date('d-m-Y'),
          'anterior' => '-1 year',
          'compras' => 0,
          'compras_anterior' => 0,
          'compras_mejora' => 0,
          'ventas' => 0,
          'ventas_anterior' => 0,
          'ventas_mejora' => 0,
          'impuestos' => 0,
          'impuestos_anterior' => 0,
          'impuestos_mejora' => 0,
          'beneficios' => 0,
          'beneficios_anterior' => 0,
          'beneficios_mejora' => 0,
      );
      $this->calcula_periodo($this->anyo);
      
      $this->leer_noticias();
   }
   
   private function calcula_periodo(&$stats, $pendiente = FALSE)
   {
      if( $this->db->table_exists('facturascli') )
      {
         /// calculamos las ventas de este mes
         $sql = "select sum(totaleuros) as total from facturascli where"
                 . " fecha >= ".$this->empresa->var2str($stats['desde'])
                 . " and fecha <= ".$this->empresa->var2str($stats['hasta']).";";
         
         $data = $this->db->select($sql);
         if($data)
         {
            $stats['ventas'] = $this->euro_convert( floatval($data[0]['total']) );
         }
         
         /// calculamos las ventas del mes pasado
         $sql = "select sum(totaleuros) as total from facturascli where"
                 . " fecha >= ".$this->empresa->var2str(date('d-m-Y', strtotime($stats['desde'].' '.$stats['anterior'])))
                 . " and fecha <= ".$this->empresa->var2str(date('d-m-Y', strtotime($stats['hasta'].' '.$stats['anterior']))).";";
         
         $data = $this->db->select($sql);
         if($data)
         {
            $stats['ventas_anterior'] = $this->euro_convert( floatval($data[0]['total']) );
            $stats['ventas_mejora'] = $this->calcular_diff($stats['ventas'], $stats['ventas_anterior']);
         }
         
         /// calculamos los impuestos de las ventas de este mes
         $sql = "select sum((totaliva+totalrecargo-totalirpf)/tasaconv) as total from facturascli where"
                 . " fecha >= ".$this->empresa->var2str($stats['desde'])
                 . " and fecha <= ".$this->empresa->var2str($stats['hasta']).";";
         
         $data = $this->db->select($sql);
         if($data)
         {
            $stats['impuestos'] = $this->euro_convert( floatval($data[0]['total']) );
         }
         
         /// calculamos los impuestos de las ventas del mes anterior
         $sql = "select sum((totaliva+totalrecargo-totalirpf)/tasaconv) as total from facturascli where"
                 . " fecha >= ".$this->empresa->var2str(date('d-m-Y', strtotime($stats['desde'].' '.$stats['anterior'])))
                 . " and fecha <= ".$this->empresa->var2str(date('d-m-Y', strtotime($stats['hasta'].' '.$stats['anterior']))).";";
         
         $data = $this->db->select($sql);
         if($data)
         {
            $stats['impuestos_anterior'] = $this->euro_convert( floatval($data[0]['total']) );
         }
      }
      
      if( $this->db->table_exists('facturasprov') )
      {
         /// calculamos las compras de este mes
         $sql = "select sum(totaleuros) as total from facturasprov where"
                 . " fecha >= ".$this->empresa->var2str($stats['desde'])
                 . " and fecha <= ".$this->empresa->var2str($stats['hasta']).";";
         
         $data = $this->db->select($sql);
         if($data)
         {
            $stats['compras'] = $this->euro_convert( floatval($data[0]['total']) );
         }
         
         /// calculamos las compras del mes pasado
         $sql = "select sum(totaleuros) as total from facturasprov where"
                 . " fecha >= ".$this->empresa->var2str(date('d-m-Y', strtotime($stats['desde'].' '.$stats['anterior'])))
                 . " and fecha <= ".$this->empresa->var2str(date('d-m-Y', strtotime($stats['hasta'].' '.$stats['anterior']))).";";
         
         $data = $this->db->select($sql);
         if($data)
         {
            $stats['compras_anterior'] = $this->euro_convert( floatval($data[0]['total']) );
            $stats['compras_mejora'] = $this->calcular_diff($stats['compras'], $stats['compras_anterior']);
         }
         
         /// calculamos los impuestos de las compras de este mes
         $sql = "select sum((totaliva+totalrecargo-totalirpf)/tasaconv) as total from facturasprov where"
                 . " fecha >= ".$this->empresa->var2str($stats['desde'])
                 . " and fecha <= ".$this->empresa->var2str($stats['hasta']).";";
         
         $data = $this->db->select($sql);
         if($data)
         {
            $stats['impuestos'] -= $this->euro_convert( floatval($data[0]['total']) );
            if($stats['impuestos'] < 0)
            {
               $stats['impuestos'] = 0;
            }
         }
         
         /// calculamos los impuestos de las compras del mes anterior
         $sql = "select sum((totaliva+totalrecargo-totalirpf)/tasaconv) as total from facturasprov where"
                 . " fecha >= ".$this->empresa->var2str(date('d-m-Y', strtotime($stats['desde'].' '.$stats['anterior'])))
                 . " and fecha <= ".$this->empresa->var2str(date('d-m-Y', strtotime($stats['hasta'].' '.$stats['anterior']))).";";
         
         $data = $this->db->select($sql);
         if($data)
         {
            $stats['impuestos_anterior'] -= $this->euro_convert( floatval($data[0]['total']) );
            if($stats['impuestos_anterior'] < 0)
            {
               $stats['impuestos_anterior'] = 0;
            }
         }
      }
      
      if( $this->db->table_exists('co_partidas') AND $this->empresa->codpais == 'ESP' )
      {
         /// calculamos el saldo de todos aquellos asientos que afecten a caja y no se correspondan con facturas
         $sql = "select sum(debe-haber) as total from co_partidas where codsubcuenta LIKE '57%' and idasiento"
                 . " in (select idasiento from co_asientos where tipodocumento IS NULL"
                 . " and fecha >= ".$this->empresa->var2str($stats['desde'])
                 . " and fecha <= ".$this->empresa->var2str($stats['hasta']).");";
         
         $data = $this->db->select($sql);
         if($data)
         {
            $saldo = floatval($data[0]['total']);
            if($saldo < 0)
            {
               $stats['impuestos'] += abs($saldo);
            }
         }
         
         /// calculamos el saldo de todos aquellos asientos que afecten a caja y no se correspondan con facturas
         $sql = "select sum(debe-haber) as total from co_partidas where codsubcuenta LIKE '57%' and idasiento"
                 . " in (select idasiento from co_asientos where tipodocumento IS NULL"
                 . " and fecha >= ".$this->empresa->var2str(date('d-m-Y', strtotime($stats['desde'].' '.$stats['anterior'])))
                 . " and fecha <= ".$this->empresa->var2str(date('d-m-Y', strtotime($stats['hasta'].' '.$stats['anterior']))).");";
         
         $data = $this->db->select($sql);
         if($data)
         {
            $saldo = floatval($data[0]['total']);
            if($saldo < 0)
            {
               $stats['impuestos_anterior'] += abs($saldo);
            }
         }
      }
      
      if($pendiente)
      {
         if( $this->db->table_exists('facturascli') )
         {
            /// calculamos las facturas de venta vencidas hasta hoy
            $sql = "select sum(totaleuros) as total from facturascli where"
                    . " pagada = false"
                    . " and vencimiento <= ".$this->empresa->var2str($stats['hasta']).";";
            
            $data = $this->db->select($sql);
            if($data)
            {
               $stats['ventas_sinpagar'] = $this->euro_convert( floatval($data[0]['total']) );
            }
         }
         
         if( $this->db->table_exists('albaranescli') )
         {
            /// calculamos los albaranes de venta pendientes
            $sql = "select sum(totaleuros) as total from albaranescli where"
                    . " idfactura IS NULL;";
            
            $data = $this->db->select($sql);
            if($data)
            {
               $stats['ventas_albaranes_pte'] = $this->euro_convert( floatval($data[0]['total']) );
            }
         }
         
         if( $this->db->table_exists('pedidoscli') )
         {
            /// calculamos los pedidos de venta pendientes
            $sql = "select sum(totaleuros) as total from pedidoscli where"
                    . " status = 0;";
            
            $data = $this->db->select($sql);
            if($data)
            {
               $stats['ventas_pedidos_pte'] = $this->euro_convert( floatval($data[0]['total']) );
            }
         }
         
         if( $this->db->table_exists('facturasprov') )
         {
            /// calculamos las facturas de compra sin pagar
            $sql = "select sum(totaleuros) as total from facturasprov where"
                    . " pagada = false;";
            
            $data = $this->db->select($sql);
            if($data)
            {
               $stats['compras_sinpagar'] = $this->euro_convert( floatval($data[0]['total']) );
            }
         }
         
         if( $this->db->table_exists('albaranesprov') )
         {
            /// calculamos los albaranes de compra pendientes
            $sql = "select sum(totaleuros) as total from albaranesprov where"
                    . " idfactura IS NULL;";
            
            $data = $this->db->select($sql);
            if($data)
            {
               $stats['compras_albaranes_pte'] = $this->euro_convert( floatval($data[0]['total']) );
            }
         }
         
         if( $this->db->table_exists('pedidosprov') )
         {
            /// calculamos los pedidos de compra pendientes
            $sql = "select sum(totaleuros) as total from pedidosprov where"
                    . " idalbaran IS NULL;";
            
            $data = $this->db->select($sql);
            if($data)
            {
               $stats['compras_pedidos_pte'] = $this->euro_convert( floatval($data[0]['total']) );
            }
         }
      }
      
      $stats['impuestos_mejora'] = $this->calcular_diff($stats['impuestos'], $stats['impuestos_anterior']);
      
      $stats['beneficios'] = $stats['ventas'] - $stats['compras'] - $stats['impuestos'];
      $stats['beneficios_anterior'] = $stats['ventas_anterior'] - $stats['compras_anterior'] - $stats['impuestos_anterior'];
      $stats['beneficios_mejora'] = $this->calcular_diff($stats['beneficios'], $stats['beneficios_anterior']);
   }
   
   private function calcular_diff($nuevo, $anterior)
   {
      if($nuevo == 0 OR $anterior == 0)
      {
         if($nuevo == 0 AND $anterior > 0)
         {
            return -100;
         }
         else if($nuevo > 0 AND $anterior == 0)
         {
            return 100;
         }
         else
            return 0;
      }
      else if($nuevo != $anterior)
      {
         return ($nuevo*100/$anterior) - 100;
      }
      else
      {
         return 0;
      }
   }
   
   private function leer_noticias()
   {
      $this->noticias = $this->cache->get_array('community_changelog');
      if(!$this->noticias)
      {
         $data = file_get_contents(FS_COMMUNITY_URL.'/index.php?page=community_changelog&json=TRUE');
         if($data)
         {
            $this->noticias = json_decode($data);
            
            /// guardamos en caché
            $this->cache->set('community_changelog', $this->noticias);
         }
      }
   }
}
