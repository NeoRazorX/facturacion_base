<?php
/*
 * This file is part of FacturaScripts
 * Copyright (C) 2013-2016  Carlos Garcia Gomez  neorazorx@gmail.com
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

require_once __DIR__.'/ezpdf/Cezpdf.php';

/**
 * Permite la generación de PDFs algo más sencilla.
 */
class fs_pdf
{
   /**
    * Ruta al logotipo de la empresa.
    * @var type 
    */
   public $logo;
   
   /**
    * Documento Cezpdf
    * @var Cezpdf
    */
   public $pdf;
   
   public $table_header;
   public $table_rows;
   
   public function __construct($paper = 'a4', $orientation = 'portrait', $font = 'Helvetica')
   {
      if( !file_exists('tmp/'.FS_TMP_NAME.'pdf') )
      {
         mkdir('tmp/'.FS_TMP_NAME.'pdf');
      }
      
      $this->cargar_logo();
      
      $this->pdf = new Cezpdf($paper, $orientation);
      $this->pdf->selectFont(__DIR__."/ezpdf/fonts/".$font.".afm");
   }
   
   /**
    * Vuelca el documento PDF en la salida estándar.
    * @param type $filename
    */
   public function show($filename = 'doc.pdf')
   {
      $this->pdf->ezStream( array('Content-Disposition' => $filename) );
   }
   
   /**
    * Guarda el documento PDF en el archivo $filename
    * @param type $filename
    * @return boolean
    */
   public function save($filename)
   {
      if($filename)
      {
         if( file_exists($filename) )
         {
            unlink($filename);
         }
         
         $file = fopen($filename, 'a');
         if($file)
         {
            fwrite($file, $this->pdf->ezOutput());
            fclose($file);
            return TRUE;
         }
         else
            return TRUE;
      }
      else
         return FALSE;
   }
   
   /**
    * Devuelve la coordenada Y actual en el documento.
    * @return type
    */
   public function get_y()
   {
      return $this->pdf->y;
   }
   
   /**
    * Establece la coordenada Y actual en el documento.
    * @param type $y
    */
   public function set_y($y)
   {
      $this->pdf->ezSetY($y);
   }
   
   /**
    * Carga la ruta del logotipo de la empresa.
    */
   private function cargar_logo()
   {
      if( !file_exists(FS_MYDOCS.'images') )
      {
         @mkdir(FS_MYDOCS.'images', 0777, TRUE);
      }
      
      /**
       * Antes se guardaba el logo en el temporal.
       * Mala decisión, lo movemos.
       */
      if( file_exists('tmp/'.FS_TMP_NAME.'logo.png') )
      {
         rename('tmp/'.FS_TMP_NAME.'logo.png', FS_MYDOCS.'images/logo.png');
      }
      else if( file_exists('tmp/'.FS_TMP_NAME.'logo.jpg') )
      {
         rename('tmp/'.FS_TMP_NAME.'logo.jpg', FS_MYDOCS.'images/logo.jpg');
      }
      
      $this->logo = FALSE;
      if( file_exists(FS_MYDOCS.'images/logo.png') )
      {
         $this->logo = FS_MYDOCS.'images/logo.png';
      }
      else if( file_exists(FS_MYDOCS.'images/logo.jpg') )
      {
         $this->logo = FS_MYDOCS.'images/logo.jpg';
      }
   }
   
   /**
    * Añade la cabecera al PDF con el logotipo y los datos de la empresa.
    * @param type $empresa
    * @param int $lppag
    */
   public function generar_pdf_cabecera(&$empresa, &$lppag)
   {
      /// ¿Añadimos el logo?
      if($this->logo)
      {
         if( function_exists('imagecreatefromstring') )
         {
            $lppag -= 2; /// si metemos el logo, caben menos líneas
            
            if( substr( strtolower($this->logo), -4 ) == '.png' )
            {
               $this->pdf->addPngFromFile($this->logo, 35, 740, 80, 80);
            }
            else
            {
               $this->pdf->addJpegFromFile($this->logo, 35, 740, 80, 80);
            }
            
            $this->pdf->ez['rightMargin'] = 40;
            $this->pdf->ezText("<b>".$empresa->nombre."</b>", 12, array('justification' => 'right'));
            $this->pdf->ezText(FS_CIFNIF.": ".$empresa->cifnif, 8, array('justification' => 'right'));
            
            $direccion = $empresa->direccion . "\n";
            if($empresa->apartado)
            {
               $direccion .= ucfirst(FS_APARTADO) . ': ' . $empresa->apartado . ' - ';
            }
            
            if($empresa->codpostal)
            {
               $direccion .= 'CP: ' . $empresa->codpostal . ' - ';
            }
            
            if($empresa->ciudad)
            {
               $direccion .= $empresa->ciudad . ' - ';
            }
            
            if($empresa->provincia)
            {
               $direccion .= '(' . $empresa->provincia . ')';
            }
            
            if($empresa->telefono)
            {
               $direccion .= "\nTeléfono: " . $empresa->telefono;
            }
            
            $this->pdf->ezText($this->fix_html($direccion)."\n", 9, array('justification' => 'right'));
            $this->set_y(750);
         }
         else
         {
            die('ERROR: no se encuentra la función imagecreatefromstring(). '
                    . 'Y por tanto no se puede usar el logotipo en los documentos.');
         }
      }
      else
      {
         $this->pdf->ezText("<b>".$empresa->nombre."</b>", 16, array('justification' => 'center'));
         $this->pdf->ezText(FS_CIFNIF.": ".$empresa->cifnif, 8, array('justification' => 'center'));
         
         $direccion = $empresa->direccion;
         if($empresa->apartado)
         {
            $direccion .= ' - ' . ucfirst(FS_APARTADO) . ': ' . $empresa->apartado;
         }
         
         if($empresa->codpostal)
         {
            $direccion .= ' - CP: ' . $empresa->codpostal;
         }
         
         if($empresa->ciudad)
         {
            $direccion .= ' - ' . $empresa->ciudad;
         }
         
         if($empresa->provincia)
         {
            $direccion .= ' (' . $empresa->provincia . ')';
         }
         
         if($empresa->telefono)
         {
            $direccion .= ' - Teléfono: ' . $empresa->telefono;
         }
         
         $this->pdf->ezText($this->fix_html($direccion), 9, array('justification' => 'center'));
      }
   }
   
   public function center_text($word = '', $tot_width = 140)
   {
      if( strlen($word) == $tot_width )
      {
         return $word;
      }
      else if( strlen($word) < $tot_width )
      {
         return $this->center_text2($word, $tot_width);
      }
      else
      {
         $result = '';
         $nword = '';
         foreach( explode(' ', $word) as $aux )
         {
            if($nword == '')
            {
               $nword = $aux;
            }
            else if( strlen($nword) + strlen($aux) + 1 <= $tot_width )
            {
               $nword = $nword.' '.$aux;
            }
            else
            {
               if($result != '')
               {
                  $result .= "\n";
               }
               $result .= $this->center_text2($nword, $tot_width);
               $nword = $aux;
            }
         }
         if($nword != '')
         {
            if($result != '')
            {
               $result .= "\n";
            }
            $result .= $this->center_text2($nword, $tot_width);
         }
         return $result;
      }
   }
   
   private function center_text2($word = '', $tot_width = 140)
   {
      $symbol = " ";
      $middle = round($tot_width / 2);
      $length_word = strlen($word);
      $middle_word = round($length_word / 2);
      $last_position = $middle + $middle_word;
      $number_of_spaces = $middle - $middle_word;
      $result = sprintf("%'{$symbol}{$last_position}s", $word);
      for($i = 0; $i < $number_of_spaces; $i++)
      {
         $result .= "$symbol";
      }
      return $result;
   }
   
   public function new_table()
   {
      $this->table_header = array();
      $this->table_rows = array();
   }
   
   public function add_table_header($header)
   {
      $this->table_header = $header;
   }
   
   public function add_table_row($row)
   {
      $this->table_rows[] = $row;
   }
   
   public function save_table($options)
   {
      if( !$this->table_header )
      {
         foreach( array_keys($this->table_rows[0]) as $k )
         {
            $this->table_header[$k] = '';
         }
      }
      
      $this->pdf->ezTable($this->table_rows, $this->table_header, '', $options);
   }
   
   public function fix_html($txt)
   {
      $newt = str_replace('&lt;', '<', $txt);
      $newt = str_replace('&gt;', '>', $newt);
      $newt = str_replace('&quot;', '"', $newt);
      $newt = str_replace('&#39;', "'", $newt);
      return $newt;
   }
}
