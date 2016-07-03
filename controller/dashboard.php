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
   public $mes;
   public $trimestre;
   public $anyo;
   
   public function __construct()
   {
      parent::__construct(__CLASS__, 'Dashboard', 'informes');
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
      $this->calcula_periodo($this->mes);
      
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
         default:
         case '1':
         case '2':
         case '3':
            $this->trimestre['desde'] = date('1-1-Y');
            $this->trimestre['hasta'] = date('t-3-Y');
            break;
         
         case '4':
         case '5':
         case '6':
            $this->trimestre['desde'] = date('1-4-Y');
            $this->trimestre['hasta'] = date('t-6-Y');
            break;
         
         case '7':
         case '8':
         case '9':
            $this->trimestre['desde'] = date('1-7-Y');
            $this->trimestre['hasta'] = date('t-9-Y');
            break;
         
         case '10':
         case '11':
         case '12':
            $this->trimestre['desde'] = date('1-10-Y');
            $this->trimestre['hasta'] = date('t-12-Y');
            break;
      }
      $this->calcula_periodo($this->trimestre);
      
      $this->anyo = array(
          'desde' => date('1-1-Y'),
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
   }
   
   private function calcula_periodo(&$stats)
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
         }
         
         /// calculamos los impuestos de las compras del mes anterior
         $sql = "select sum((totaliva+totalrecargo-totalirpf)/tasaconv) as total from facturasprov where"
                 . " fecha >= ".$this->empresa->var2str(date('d-m-Y', strtotime($stats['desde'].' '.$stats['anterior'])))
                 . " and fecha <= ".$this->empresa->var2str(date('d-m-Y', strtotime($stats['hasta'].' '.$stats['anterior']))).";";
         
         $data = $this->db->select($sql);
         if($data)
         {
            $stats['impuestos_anterior'] -= $this->euro_convert( floatval($data[0]['total']) );
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
}
