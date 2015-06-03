<?php
/*
 * This file is part of FacturaSctipts
 * Copyright (C) 2013-2015  Carlos Garcia Gomez  neorazorx@gmail.com
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 * 
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

require_model('albaran_cliente.php');
require_model('albaran_proveedor.php');
require_model('familia.php');
require_model('impuesto.php');
require_model('stock.php');

/**
 * Almacena los datos de un artículos.
 */
class articulo extends fs_model
{
   /**
    * Clave primaria. Varchar (18).
    * @var type 
    */
   public $referencia;
   
   /**
    * Define el tipo de artículo, así se pueden establecer distinciones
    * según un tipo u otro.
    * @var type Varchar (10).
    */
   public $tipo;
   
   /**
    * Código de la familia a la que pertenece. En la clase familia.
    * @var type 
    */
   public $codfamilia;
   public $descripcion;
   
   public $pvp;
   
   /**
    * Almacena el valor del pvp antes de hacer el cambio.
    * Esta valor no se almacena en la base de datos, es decir,
    * no se recuerda.
    * @var type 
    */
   public $pvp_ant;
   
   /**
    * Fecha de actualización del pvp.
    * @var type 
    */
   public $factualizado;
   
   /**
    * Coste medio al comprar el artículo. Calculado.
    * @var type 
    */
   public $costemedio;
   
   /**
    * Precio de coste editado manualmente.
    * @var type 
    */
   public $preciocoste;
   
   /**
    * Impuesto asignado. Clase impuesto.
    * @var type 
    */
   public $codimpuesto;
   
   /**
    * % IVA del impuesto asignado.
    * @var type 
    */
   public $iva;
   public $bloqueado;
   public $secompra;
   public $sevende;
   
   /**
    * TRUE -> se mostrará sincronizará con la tienda online.
    * @var type 
    */
   public $publico;
   
   /**
    * Código de equivalencia. Varchar (18).
    * Dos artículos o más son equivalentes si tienen el mismo código de equivalencia.
    * @var type 
    */
   public $equivalencia;
   
   /**
    * Stock físico. La suma de las cantidades de esta referencia que en la tabla stocks.
    * @var type 
    */
   public $stockfis;
   public $stockmin;
   public $stockmax;
   
   /**
    * TRUE -> permitir ventas sin stock.
    * Si, sé que no tiene sentido que poner controlstock a TRUE
    * implique la ausencia de control de stock. Pero es una cagada de
    * FacturaLux -> Abanq -> Eneboo, y por motivos de compatibilidad
    * se mantiene.
    * @var type 
    */
   public $controlstock;
   
   /**
    * Código de barras.
    * @var type 
    */
   public $codbarras;
   public $observaciones;
   
   private $imagen;
   private $has_imagen;
   private $exists;
   
   private static $impuestos;
   private static $search_tags;
   private static $cleaned_cache;
   private static $column_list;


   public function __construct($a=FALSE)
   {
      parent::__construct('articulos', 'plugins/facturacion_base/');
      
      if( !isset(self::$impuestos) )
      {
         self::$impuestos = array();
      }
      
      if( !isset(self::$column_list) )
      {
         self::$column_list = 'referencia,codfamilia,descripcion,pvp,factualizado,costemedio,'.
                 'preciocoste,codimpuesto,stockfis,stockmin,stockmax,controlstock,bloqueado,'.
                 'secompra,sevende,equivalencia,codbarras,observaciones,imagen,publico,tipo';
      }
      
      if($a)
      {
         $this->referencia = $a['referencia'];
         $this->tipo = $a['tipo'];
         $this->codfamilia = $a['codfamilia'];
         $this->descripcion = $this->no_html($a['descripcion']);
         $this->pvp = floatval($a['pvp']);
         $this->factualizado = Date('d-m-Y', strtotime($a['factualizado']));
         $this->costemedio = floatval($a['costemedio']);
         $this->preciocoste = floatval($a['preciocoste']);
         $this->codimpuesto = $a['codimpuesto'];
         $this->stockfis = floatval($a['stockfis']);
         $this->stockmin = floatval($a['stockmin']);
         $this->stockmax = floatval($a['stockmax']);
         $this->controlstock = $this->str2bool($a['controlstock']);
         $this->bloqueado = $this->str2bool($a['bloqueado']);
         $this->secompra = $this->str2bool($a['secompra']);
         $this->sevende = $this->str2bool($a['sevende']);
         $this->publico = $this->str2bool($a['publico']);
         $this->equivalencia = $a['equivalencia'];
         $this->codbarras = $a['codbarras'];
         $this->observaciones = $this->no_html($a['observaciones']);
         
         /// no cargamos la imagen directamente por cuestión de rendimiento
         $this->imagen = NULL;
         $this->has_imagen = isset($a['imagen']);
         $this->exists = TRUE;
      }
      else
      {
         $this->referencia = NULL;
         $this->tipo = NULL;
         $this->codfamilia = NULL;
         $this->descripcion = '';
         $this->pvp = 0;
         $this->factualizado = Date('d-m-Y');
         $this->costemedio = 0;
         $this->preciocoste = 0;
         $this->codimpuesto = NULL;
         $this->stockfis = 0;
         $this->stockmin = 0;
         $this->stockmax = 0;
         $this->controlstock = (bool)FS_VENTAS_SIN_STOCK;
         $this->bloqueado = FALSE;
         $this->secompra = TRUE;
         $this->sevende = TRUE;
         $this->publico = FALSE;
         $this->equivalencia = NULL;
         $this->codbarras = '';
         $this->observaciones = '';
         
         $this->imagen = NULL;
         $this->has_imagen = FALSE;
         $this->exists = FALSE;
      }
      
      $this->pvp_ant = 0;
      $this->iva = NULL;
   }
   
   protected function install()
   {
      /// la tabla articulos tiene claves ajeas a familias, impuestos y stocks
      new familia();
      new impuesto();
      
      $this->clean_cache();
      
      /// borramos todas las imágenes de artículos
      if( file_exists('tmp/articulos') )
      {
         foreach(glob('tmp/articulos/*') as $file)
         {
            if( is_file($file) )
               unlink($file);
         }
      }
      
      return '';
   }
   
   public function get_descripcion_64()
   {
      return base64_encode($this->descripcion);
   }
   
   public function pvp_iva()
   {
      return $this->pvp * (100+$this->get_iva()) / 100;
   }
   
   public function costemedio_iva()
   {
      return $this->costemedio * (100+$this->get_iva()) / 100;
   }
   
   public function preciocoste()
   {
      if(FS_COST_IS_AVERAGE)
      {
         return $this->costemedio;
      }
      else
         return $this->preciocoste;
   }
   
   public function preciocoste_iva()
   {
      return $this->preciocoste() * (100+$this->get_iva()) / 100;
   }
   
   public function factualizado()
   {
      return $this->var2timesince($this->factualizado);
   }
   
   public function url()
   {
      if( is_null($this->referencia) )
      {
         return "index.php?page=ventas_articulos";
      }
      else
         return "index.php?page=ventas_articulo&ref=".urlencode($this->referencia);
   }
   
   public function get($ref)
   {
      $art = $this->db->select("SELECT ".self::$column_list." FROM ".$this->table_name." WHERE referencia = ".$this->var2str($ref).";");
      if($art)
      {
         return new articulo($art[0]);
      }
      else
         return FALSE;
   }
   
   public function get_familia()
   {
      $fam = new familia();
      return $fam->get($this->codfamilia);
   }
   
   public function get_stock()
   {
      $stock = new stock();
      return $stock->all_from_articulo($this->referencia);
   }
   
   public function get_impuesto()
   {
      $imp = new impuesto();
      return $imp->get($this->codimpuesto);
   }
   
   public function get_iva()
   {
      if( is_null($this->iva) )
      {
         $encontrado = FALSE;
         foreach(self::$impuestos as $i)
         {
            if($i->codimpuesto == $this->codimpuesto)
            {
               $this->iva = floatval($i->iva);
               $encontrado = TRUE;
               break;
            }
         }
         if( !$encontrado )
         {
            $imp = new impuesto();
            $imp0 = $imp->get($this->codimpuesto);
            if($imp0)
            {
               $this->iva = floatval($imp0->iva);
               self::$impuestos[] = $imp0;
            }
            else
               $this->iva = 0;
         }
      }
      return $this->iva;
   }
   
   public function get_equivalentes()
   {
      $artilist = array();
      
      if( isset($this->equivalencia) )
      {
         $data = $this->db->select("SELECT ".self::$column_list." FROM ".$this->table_name.
                 " WHERE equivalencia = ".$this->var2str($this->equivalencia)." ORDER BY referencia ASC;");
         if($data)
         {
            foreach($data as $d)
            {
               if($d['referencia'] != $this->referencia)
               {
                  $artilist[] = new articulo($d);
               }
            }
         }
      }
      
      return $artilist;
   }
   
   public function get_lineas_albaran_cli($offset=0, $limit=FS_ITEM_LIMIT)
   {
      $linea = new linea_albaran_cliente();
      return $linea->all_from_articulo($this->referencia, $offset, $limit);
   }
   
   public function get_lineas_albaran_prov($offset=0, $limit=FS_ITEM_LIMIT)
   {
      $linea = new linea_albaran_proveedor();
      return $linea->all_from_articulo($this->referencia, $offset, $limit);
   }
   
   /**
    * Devuelve la media del precio de compra del artículo
    * @return type
    */
   public function get_costemedio()
   {
      $coste = 0;
      $stock = 0;
      
      foreach($this->get_lineas_albaran_prov() as $linea)
      {
         if($stock < $this->stockfis)
         {
            $coste += $linea->pvptotal;
            $stock += $linea->cantidad;
         }
      }
      
      if($stock > 0)
      {
         return $coste/$stock;
      }
      else
         return $coste;
   }
   
   public function imagen_url()
   {
      if( $this->has_imagen )
      {
         if( file_exists('tmp/articulos/'.$this->referencia.'.png') )
         {
            return 'tmp/articulos/'.$this->referencia.'.png';
         }
         else
         {
            if( is_null($this->imagen) )
            {
               $imagen = $this->db->select("SELECT imagen FROM ".$this->table_name." WHERE referencia = ".$this->var2str($this->referencia).";");
               if($imagen)
               {
                  $this->imagen = $this->str2bin($imagen[0]['imagen']);
               }
               else
                  $this->has_imagen = FALSE;
            }
            
            if( isset($this->imagen) )
            {
               if( !file_exists('tmp/articulos') )
                  mkdir('tmp/articulos');
               
               $f = fopen('tmp/articulos/'.$this->referencia.'.png', 'a');
               fwrite($f, $this->imagen);
               fclose($f);
               return 'tmp/articulos/'.$this->referencia.'.png';
            }
            else
               return FALSE;
         }
      }
      else
         return FALSE;
   }
   
   public function set_imagen($img)
   {
      if( is_null($img) )
      {
         $this->imagen = NULL;
         $this->has_imagen = FALSE;
         $this->clean_image_cache();
      }
      else
      {
         $this->imagen = $img;
         $this->has_imagen = TRUE;
      }
   }
   
   public function set_pvp($p)
   {
      $this->pvp_ant = $this->pvp;
      $this->factualizado = Date('d-m-Y');
      $this->pvp = round($p, 3);
   }
   
   public function set_pvp_iva($p)
   {
      $this->pvp_ant = $this->pvp;
      $this->factualizado = Date('d-m-Y');
      $this->pvp = round((100*$p)/(100+$this->get_iva()), 3);
   }
   
   public function set_referencia($ref)
   {
      $ref = str_replace(' ', '_', trim($ref));
      if( !preg_match("/^[A-Z0-9_\+\.\*\/\-]{1,18}$/i", $ref) )
      {
         $this->new_error_msg("¡Referencia de artículo no válida! Debe tener entre 1 y 18 caracteres.
            Se admiten letras, números, '_', '.', '*', '/' ó '-'.");
      }
      else if($ref != $this->referencia)
      {
         $sql = "UPDATE ".$this->table_name." SET referencia = ".$this->var2str($ref)." WHERE referencia = ".$this->var2str($this->referencia).";";
         if( $this->db->exec($sql) )
         {
            $this->referencia = $ref;
         }
         else
         {
            $this->new_error_msg('Imposible modificar la referencia.');
         }
      }
   }
   
   public function set_impuesto($codimpuesto)
   {
      if($codimpuesto != $this->codimpuesto)
      {
         $this->codimpuesto = $codimpuesto;
         
         $encontrado = FALSE;
         foreach(self::$impuestos as $i)
         {
            if($i->codimpuesto == $this->codimpuesto)
            {
               $this->iva = floatval($i->iva);
               $encontrado = TRUE;
               break;
            }
         }
         if( !$encontrado )
         {
            $imp = new impuesto();
            $imp0 = $imp->get($this->codimpuesto);
            if($imp0)
            {
               $this->iva = floatval($imp0->iva);
               self::$impuestos[] = $imp0;
            }
            else
               $this->iva = 0;
         }
      }
   }
   
   public function set_stock($almacen, $cantidad=1)
   {
      $result = FALSE;
      $stock = new stock();
      $encontrado = FALSE;
      
      $stocks = $stock->all_from_articulo($this->referencia);
      foreach($stocks as $k => $value)
      {
         if($value->codalmacen == $almacen)
         {
            $stocks[$k]->set_cantidad($cantidad);
            $result = $stocks[$k]->save();
            $encontrado = TRUE;
            break;
         }
      }
      if( !$encontrado )
      {
         $stock->referencia = $this->referencia;
         $stock->codalmacen = $almacen;
         $stock->set_cantidad($cantidad);
         $result = $stock->save();
      }
      
      if($result)
      {
         $nuevo_stock = $stock->total_from_articulo($this->referencia);
         if($this->stockfis != $nuevo_stock)
         {
            $this->stockfis =  $nuevo_stock;
            
            if($this->exists)
            {
               $this->clean_cache();
               $result = $this->db->exec("UPDATE ".$this->table_name." SET stockfis = ".$this->var2str($this->stockfis)."
                  WHERE referencia = ".$this->var2str($this->referencia).";");
            }
            else if( !$this->save() )
            {
               $this->new_error_msg("¡Error al actualizar el stock del artículo!");
            }
         }
      }
      else
         $this->new_error_msg("Error al guardar el stock");
      
      return $result;
   }
   
   public function sum_stock($almacen, $cantidad=1, $recalcula_coste=FALSE)
   {
      $result = FALSE;
      $stock = new stock();
      $encontrado = FALSE;
      
      $stocks = $stock->all_from_articulo($this->referencia);
      foreach($stocks as $k => $value)
      {
         if($value->codalmacen == $almacen)
         {
            $stocks[$k]->sum_cantidad($cantidad);
            $result = $stocks[$k]->save();
            $encontrado = TRUE;
            break;
         }
      }
      if( !$encontrado )
      {
         $stock->referencia = $this->referencia;
         $stock->codalmacen = $almacen;
         $stock->set_cantidad($cantidad);
         $result = $stock->save();
      }
      
      if($result)
      {
         $nuevo_stock = $stock->total_from_articulo($this->referencia);
         if($this->stockfis != $nuevo_stock)
         {
            $this->stockfis =  $nuevo_stock;
            if($recalcula_coste)
            {
               $this->costemedio = $this->get_costemedio();
            }
            
            if($this->exists)
            {
               $this->clean_cache();
               $result = $this->db->exec("UPDATE ".$this->table_name." SET stockfis = ".$this->var2str($this->stockfis).",
                  costemedio = ".$this->var2str($this->costemedio)." WHERE referencia = ".$this->var2str($this->referencia).";");
            }
            else if( !$this->save() )
            {
               $this->new_error_msg("¡Error al actualizar el stock del artículo!");
            }
         }
      }
      else
         $this->new_error_msg("¡Error al guardar el stock!");
      
      return $result;
   }
   
   /**
    * Esta función devuelve TRUE si el artículo ya existe en la base de datos.
    * Por motivos de rendimiento y al ser esta una clase de uso intensivo,
    * se utiliza la variable $this->exists para almacenar el resultado.
    * @return type
    */
   public function exists()
   {
      if( !$this->exists )
      {
         if( $this->db->select("SELECT referencia FROM ".$this->table_name." WHERE referencia = ".$this->var2str($this->referencia).";") )
         {
            $this->exists = TRUE;
         }
      }
      
      return $this->exists;
   }
   
   public function test()
   {
      $status = FALSE;
      
      /// cargamos la imágen si todavía no lo habíamos hecho
      if( $this->has_imagen )
      {
         if( is_null($this->imagen) )
         {
            $imagen = $this->db->select("SELECT imagen FROM ".$this->table_name." WHERE referencia = ".$this->var2str($this->referencia).";");
            if($imagen)
            {
               $this->imagen = $this->str2bin($imagen[0]['imagen']);
            }
            else
            {
               $this->imagen = NULL;
               $this->has_imagen = FALSE;
            }
         }
      }
      
      $this->descripcion = $this->no_html($this->descripcion);
      $this->codbarras = $this->no_html($this->codbarras);
      $this->observaciones = $this->no_html($this->observaciones);
      
      $this->equivalencia = str_replace(' ', '_', trim($this->equivalencia));
      if($this->equivalencia == '')
      {
         $this->equivalencia = NULL;
      }
      
      if( !preg_match("/^[A-Z0-9_\+\.\*\/\-]{1,18}$/i", $this->referencia) )
      {
         $this->new_error_msg("¡Referencia de artículo no válida! Debe tener entre 1 y 18 caracteres.
            Se admiten letras (excepto Ñ), números, '_', '.', '*', '/' ó '-'.");
      }
      else if( isset($this->equivalencia) AND !preg_match("/^[A-Z0-9_\+\.\*\/\-]{1,25}$/i", $this->equivalencia) )
      {
         $this->new_error_msg("¡Código de equivalencia del artículos no válido! Debe tener entre 1 y 25 caracteres.
            Se admiten letras (excepto Ñ), números, '_', '.', '*', '/' ó '-'.");
      }
      else
         $status = TRUE;
      
      return $status;
   }
   
   public function save()
   {
      if( $this->test() )
      {
         $this->clean_cache();
         $this->clean_image_cache();
         
         if( $this->exists() )
         {
            $sql = "UPDATE ".$this->table_name." SET descripcion = ".$this->var2str($this->descripcion).
                    ", codfamilia = ".$this->var2str($this->codfamilia).
                    ", pvp = ".$this->var2str($this->pvp).
                    ", factualizado = ".$this->var2str($this->factualizado).
                    ", costemedio = ".$this->var2str($this->costemedio).
                    ", preciocoste = ".$this->var2str($this->preciocoste).
                    ", codimpuesto = ".$this->var2str($this->codimpuesto).
                    ", stockfis = ".$this->var2str($this->stockfis).
                    ", stockmin = ".$this->var2str($this->stockmin).
                    ", stockmax = ".$this->var2str($this->stockmax).
                    ", controlstock = ".$this->var2str($this->controlstock).
                    ", bloqueado = ".$this->var2str($this->bloqueado).
                    ", sevende = ".$this->var2str($this->sevende).
                    ", publico = ".$this->var2str($this->publico).
                    ", secompra = ".$this->var2str($this->secompra).
                    ", equivalencia = ".$this->var2str($this->equivalencia).
                    ", codbarras = ".$this->var2str($this->codbarras).
                    ", observaciones = ".$this->var2str($this->observaciones).
                    ", tipo = ".$this->var2str($this->tipo).
                    ", imagen = ".$this->bin2str($this->imagen).
                    " WHERE referencia = ".$this->var2str($this->referencia).";";
         }
         else
         {
            $sql = "INSERT INTO ".$this->table_name." (".self::$column_list.") VALUES (".
                    $this->var2str($this->referencia).",".
                    $this->var2str($this->codfamilia).",".
                    $this->var2str($this->descripcion).",".
                    $this->var2str($this->pvp).",".
                    $this->var2str($this->factualizado).",".
                    $this->var2str($this->costemedio).",".
                    $this->var2str($this->preciocoste).",".
                    $this->var2str($this->codimpuesto).",".
                    $this->var2str($this->stockfis).",".
                    $this->var2str($this->stockmin).",".
                    $this->var2str($this->stockmax).",".
                    $this->var2str($this->controlstock).",".
                    $this->var2str($this->bloqueado).",".
                    $this->var2str($this->secompra).",".
                    $this->var2str($this->sevende).",".
                    $this->var2str($this->equivalencia).",".
                    $this->var2str($this->codbarras).",".
                    $this->var2str($this->observaciones).",".
                    $this->bin2str($this->imagen).",".
                    $this->var2str($this->publico).",".
                    $this->var2str($this->tipo).");";
         }
         
         if( $this->db->exec($sql) )
         {
            $this->exists = TRUE;
            return TRUE;
         }
         else
            return FALSE;
      }
      else
         return FALSE;
   }
   
   public function delete()
   {
      $this->clean_cache();
      $this->clean_image_cache();
      
      $sql = "DELETE FROM ".$this->table_name." WHERE referencia = ".$this->var2str($this->referencia).";";
      if( $this->db->exec($sql) )
      {
         $this->exists = FALSE;
         return TRUE;
      }
      else
         return FALSE;
   }
   
   /**
    * Comprueba y añade una cadena a la lista de búsquedas precargadas
    * en memcache. Devuelve TRUE si la cadena ya está en la lista de
    * precargadas.
    * @param type $tag
    * @return boolean
    */
   private function new_search_tag($tag)
   {
      $encontrado = FALSE;
      $actualizar = FALSE;
      
      if( strlen($tag) > 1 )
      {
         /// obtenemos los datos de memcache
         $this->get_search_tags();
         
         foreach(self::$search_tags as $i => $value)
         {
            if( $value['tag'] == $tag )
            {
               $encontrado = TRUE;
               if( time()+5400 > $value['expires']+300 )
               {
                  self::$search_tags[$i]['count']++;
                  self::$search_tags[$i]['expires'] = time() + (self::$search_tags[$i]['count'] * 5400);
                  $actualizar = TRUE;
               }
               break;
            }
         }
         if( !$encontrado )
         {
            self::$search_tags[] = array('tag' => $tag, 'expires' => time()+5400, 'count' => 1);
            $actualizar = TRUE;
         }
         
         if( $actualizar )
            $this->cache->set('articulos_searches', self::$search_tags, 5400);
      }
      
      return $encontrado;
   }
   
   public function get_search_tags()
   {
      if( !isset(self::$search_tags) )
      {
         self::$search_tags = $this->cache->get_array('articulos_searches');
      }
      
      return self::$search_tags;
   }
   
   public function cron_job()
   {
      /// aceleramos las búsquedas
      if( $this->get_search_tags() )
      {
         foreach(self::$search_tags as $i => $value)
         {
            if( $value['expires'] < time() )
            {
               /// eliminamos las búsquedas antiguas
               unset(self::$search_tags[$i]);
            }
            else if( $value['count'] > 1 )
            {
               /// guardamos los resultados de la búsqueda en memcache
               $this->cache->set('articulos_search_'.$value['tag'], $this->search($value['tag']), 5400);
               echo '.';
            }
         }
         
         /// guardamos en memcache la lista de búsquedas
         $this->cache->set('articulos_searches', self::$search_tags, 5400);
      }
   }
   
   private function clean_image_cache()
   {
      if( file_exists('tmp/articulos/'.$this->referencia.'.png') )
      {
         if( !unlink('tmp/articulos/'.$this->referencia.'.png') )
         {
            $this->new_error_msg('Error al borrar la imagen de tmp.');
         }
      }
   }
   
   private function clean_cache()
   {
      /*
       * Durante las actualizaciones masivas de artículos se ejecuta esta
       * función cada vez que se guarda un artículo, por eso es mejor limitarla.
       */
      if( !self::$cleaned_cache )
      {
         /// obtenemos los datos de memcache
         $this->get_search_tags();
         
         if( self::$search_tags )
         {
            foreach(self::$search_tags as $value)
               $this->cache->delete('articulos_search_'.$value['tag']);
         }
         
         self::$cleaned_cache = TRUE;
      }
   }
   
   public function search($query, $offset=0, $codfamilia='', $con_stock=FALSE)
   {
      $artilist = array();
      $query = $this->no_html( strtolower($query) );
      
      if($offset == 0 AND $codfamilia == '' AND !$con_stock)
      {
         /// intentamos obtener los datos de memcache
         if( $this->new_search_tag($query) )
         {
            $artilist = $this->cache->get_array('articulos_search_'.$query);
         }
         else
         {
            /// buscamos la referencia completa, para ponerlo el primero
            $data = $this->db->select("SELECT ".self::$column_list." FROM ".$this->table_name." WHERE lower(referencia) = ".$this->var2str($query).";");
            if($data)
            {
               $artilist[] = new articulo($data[0]);
            }
         }
      }
      
      if( count($artilist) <= 1 )
      {
         if($codfamilia == '')
         {
            $sql = "SELECT ".self::$column_list." FROM ".$this->table_name." WHERE ";
         }
         else
            $sql = "SELECT ".self::$column_list." FROM ".$this->table_name." WHERE codfamilia = ".$this->var2str($codfamilia)." AND ";
         
         if($con_stock)
         {
            $sql .= "stockfis > 0 AND ";
         }
         
         if( is_numeric($query) )
         {
            $sql .= "(referencia LIKE '%".$query."%' OR equivalencia LIKE '%".$query."%' OR descripcion LIKE '%".$query."%'
               OR codbarras = '".$query."')";
         }
         else
         {
            $buscar = str_replace(' ', '%', $query);
            $sql .= "(lower(referencia) LIKE '%".$buscar."%' OR lower(equivalencia) LIKE '%".$buscar."%'
               OR lower(descripcion) LIKE '%".$buscar."%')";
         }
         
         $sql .= " ORDER BY referencia ASC";
         
         $articulos = $this->db->select_limit($sql, FS_ITEM_LIMIT, $offset);
         if($articulos)
         {
            foreach($articulos as $a)
            {
               /// PUEDE que hayamos puesto el primero al artćiulo con la referencia exacta
               if($a['referencia'] != $query OR count($artilist) == 0)
               {
                  $artilist[] = new articulo($a);
               }
            }
         }
      }
      
      return $artilist;
   }
   
   public function search_by_codbar($cod, $offset=0, $limit=FS_ITEM_LIMIT)
   {
      $artilist = array();
      
      $sql = "SELECT ".self::$column_list." FROM ".$this->table_name." WHERE codbarras = ".$this->var2str($cod)." ORDER BY referencia ASC";
      $data = $this->db->select_limit($sql, $limit, $offset);
      if($data)
      {
         foreach($data as $d)
            $artilist[] = new articulo($d);
      }
      
      return $artilist;
   }
   
   public function all($offset=0, $limit=FS_ITEM_LIMIT)
   {
      $artilist = array();
      
      $data = $this->db->select_limit("SELECT ".self::$column_list." FROM ".$this->table_name." ORDER BY referencia ASC", $limit, $offset);
      if($data)
      {
         foreach($data as $d)
            $artilist[] = new articulo($d);
      }
      
      return $artilist;
   }
   
   public function all_publico($offset=0, $limit=FS_ITEM_LIMIT)
   {
      $artilist = array();
      
      $data = $this->db->select_limit("SELECT ".self::$column_list." FROM ".$this->table_name." WHERE publico ORDER BY referencia ASC", $limit, $offset);
      if($data)
      {
         foreach($data as $d)
            $artilist[] = new articulo($d);
      }
      
      return $artilist;
   }
   
   public function all_from_familia($cod, $offset=0, $limit=FS_ITEM_LIMIT)
   {
      $artilist = array();
      
      $sql = "SELECT ".self::$column_list." FROM ".$this->table_name." WHERE codfamilia = ".$this->var2str($cod)." ORDER BY referencia ASC";
      $data = $this->db->select_limit($sql, $limit, $offset);
      if($data)
      {
         foreach($data as $d)
            $artilist[] = new articulo($d);
      }
      
      return $artilist;
   }
}
