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

require_once 'plugins/facturacion_base/extras/fs_pdf.php';
require_model('ejercicio.php');
require_model('empresa.php');
require_model('partida.php');
require_model('subcuenta.php');

class libro_mayor
{
   private $ejercicio;
   private $empresa;
   private $subcuenta;
   
   
   public function __construct()
   {
      $this->ejercicio = new ejercicio();
      $this->empresa = new empresa();
      $this->subcuenta = new subcuenta();
   }
   
   public function cron_job()
   {
      /**
       * Como es un proceso que tarda mucho, solamente comprobamos los dos primeros
       * ejercicios de la lista (los más nuevos), más uno aleatorio.
       */
      $ejercicios = $this->ejercicio->all();
      $random = mt_rand( 0, count($ejercicios)-1 );
      foreach($ejercicios as $num => $eje)
      {
         if($num < 2 OR $num == $random)
         {
            foreach($this->subcuenta->all_from_ejercicio($eje->codejercicio) as $sc)
            {
               if($sc->debe > 2000 OR $sc->haber > 2000)
               {
                  $sc->save();
                  $this->libro_mayor($sc, TRUE);
               }
            }
            
            $this->libro_diario($eje);
         }
      }
   }
   
      public function libro_mayor(&$subc,$mes, $echos = FALSE)
   {
      if($subc)
      {
/*         if( !file_exists('tmp/'.FS_TMP_NAME.'libro_mayor') )
            mkdir('tmp/'.FS_TMP_NAME.'libro_mayor');
         
         if( !file_exists('tmp/'.FS_TMP_NAME.'libro_mayor/'.$subc->idsubcuenta.'-'.$mes.'-'.$subc->codejercicio.'.pdf') )
         {
 */           if($echos)
               echo '.';
            
            $pdf_doc = new fs_pdf();
            $pdf_doc->pdf->addInfo('Title', 'Libro mayor de ' . $subc->codsubcuenta);
            $pdf_doc->pdf->addInfo('Subject', 'Libro mayor de ' . $subc->codsubcuenta);
            $pdf_doc->pdf->addInfo('Author', $this->empresa->nombre);
            $pdf_doc->pdf->ezStartPageNumbers(590, 10, 10, 'left', '{PAGENUM} de {TOTALPAGENUM}');
            
            $partidas = $subc->get_partidas_libros($mes,$offset=0);
            if($partidas)
            {
               $lineasfact = count($partidas);
               $linea_actual = 0;
               $lppag = 49;
               
               // Imprimimos las páginas necesarias
               while($linea_actual < $lineasfact)
               {
                  /// salto de página
                  if($linea_actual > 0)
                     $pdf_doc->pdf->ezNewPage();
					 /////  Primer encabezado
				$pdf_doc->pdf->ezText("<b>".$this->empresa->nombre."</b>", 14, array('justification' => 'left'));
				$pdf_doc->pdf->ezText("<b>Libro Mayor</b>", 12, array('justification' => 'center'));
				$pdf_doc->pdf->ezText("\n", 10);
				$pdf_doc->pdf->ezText("Fecha/Hora impresión: ".Date('d-m-Y - H:i:s'), 10, array('justification' => 'left'));
				$pdf_doc->pdf->ezText("\n", 10);
                  $pdf_doc->new_table();
                  $pdf_doc->add_table_row(
                     array(
                         'subcuenta' => "<b>Subcuenta: </b>".$subc->codsubcuenta,
						 'descripcion' =>$subc->descripcion,
                         'alias' => "<b>Alias: </b>".$subc->alias,
						 'periodo' => "<b>Periodo: </b>".$subc->get_nom_mes($mes).'  '.$subc->codejercicio,
                     )
                  );
                  $pdf_doc->save_table(
                     array(
                         'cols' => array(
                             'campos' => array('justification' => 'right', 'width' => 70),
                             'factura' => array('justification' => 'left')
                         ),
                         'showLines' => 0,
                         'width' => 540
                     )
                  );
                  $pdf_doc->pdf->ezText("\n", 10);
                  
                  
                  /// Creamos la tabla con las lineas
                  $pdf_doc->new_table();
				  $pdf_doc->add_table_row(
                     array(
                         'asiento' => "",
						 'fecha' =>"",
                         'concepto' => "Saldo Anterior",
						 'debe' => "",
						 'haber' => "",
						 'saldo' => $subc->get_partidas_saldo_anterior(),
                     )
                  );
                  $pdf_doc->add_table_header(
                     array(
                         'asiento' => '<b>Asiento</b>',
                         'fecha' => '<b>Fecha</b>',
                         'concepto' => '<b>Concepto</b>',
                         'debe' => '<b>Debe</b>',
                         'haber' => '<b>Haber</b>',
                         'saldo' => '<b>Saldo</b>'
                     )
                  );
                  for($i = $linea_actual; (($linea_actual < ($lppag + $i)) AND ($linea_actual < $lineasfact));)
                  {
                     $pdf_doc->add_table_row(
                        array(
                            'asiento' => $partidas[$linea_actual]->numero,
                            'fecha' => $partidas[$linea_actual]->fecha,
                            'concepto' => substr($partidas[$linea_actual]->concepto, 0, 60),
                            'debe' => $this->show_numero($partidas[$linea_actual]->debe),
                            'haber' => $this->show_numero($partidas[$linea_actual]->haber),
                            'saldo' => $this->show_numero($partidas[$linea_actual]->saldo)
                        )
                     );
					 $a=$linea_actual;
                     $linea_actual++;
                  }
                  /// añadimos las sumas de la línea actual
                  $pdf_doc->add_table_row(
                        array(
                            'asiento' => '',
                            'fecha' => '',
                            'concepto' => '',
                            'debe' => '<b>'.$this->show_numero($partidas[$linea_actual-1]->sum_debe).'</b>',
                            'haber' => '<b>'.$this->show_numero($partidas[$linea_actual-1]->sum_haber).'</b>',
                            'saldo' => ''
                        )
                  );
                  $pdf_doc->save_table(
                     array(
                         'fontSize' => 8,
                         'cols' => array(
                             'debe' => array('justification' => 'right'),
                             'haber' => array('justification' => 'right'),
                             'saldo' => array('justification' => 'right')
                         ),
                         'width' => 540,
                         'shaded' => 0
                     )
                  );
               }
            }

			$pdf_doc->pdf->ezText("\n", 18);
				$pdf_doc->pdf->ezText("<b>Total Debe :    </b>".$this->show_numero($partidas[$linea_actual-1]->sum_debe), 12, array('justification' => 'left'));
				$pdf_doc->pdf->ezText("\n", 12);
				$pdf_doc->pdf->ezText("<b>Total Haber :   </b>".$this->show_numero($partidas[$linea_actual-1]->sum_haber), 12, array('justification' => 'left'));
				$pdf_doc->pdf->ezText("\n", 12);
				$pdf_doc->pdf->ezText("<b>Saldo :         </b>".$this->show_numero($partidas[$linea_actual-1]->saldo), 12, array('justification' => 'left'));
            
			$pdf_doc->show();
  //          $pdf_doc->save('tmp/'.FS_TMP_NAME.'libro_mayor/'.$subc->idsubcuenta.'-'.$mes.'-'.$subc->codejercicio.'.pdf');
  //       }
      }
   }
   
   
         public function libro_mayor_ver(&$subc,$mes, $echos = FALSE)
   {
      if($subc)
      {
/*         if( !file_exists('tmp/'.FS_TMP_NAME.'libro_mayor') )
            mkdir('tmp/'.FS_TMP_NAME.'libro_mayor');
         
         if( !file_exists('tmp/'.FS_TMP_NAME.'libro_mayor/'.$subc->idsubcuenta.'-'.$mes.'-'.$subc->codejercicio.'.pdf') )
         {
 */           if($echos)
               echo '.';
            
            $pdf_doc = new fs_pdf();
            $pdf_doc->pdf->addInfo('Title', 'Libro mayor de ' . $subc->codsubcuenta);
            $pdf_doc->pdf->addInfo('Subject', 'Libro mayor de ' . $subc->codsubcuenta);
            $pdf_doc->pdf->addInfo('Author', $this->empresa->nombre);
            $pdf_doc->pdf->ezStartPageNumbers(590, 10, 10, 'left', '{PAGENUM} de {TOTALPAGENUM}');
            
            $partidas = $subc->get_partidas_libros_ver($mes,$offset=0);
            if($partidas)
            {
               $lineasfact = count($partidas);
               $linea_actual = 0;
               $lppag = 49;
               
               // Imprimimos las páginas necesarias
               while($linea_actual < $lineasfact)
               {
                  /// salto de página
                  if($linea_actual > 0)
                     $pdf_doc->pdf->ezNewPage();
					 /////  Primer encabezado
				$pdf_doc->pdf->ezText("<b>".$this->empresa->nombre."</b>", 14, array('justification' => 'left'));
				$pdf_doc->pdf->ezText("<b>Libro Mayor</b>", 12, array('justification' => 'center'));
				$pdf_doc->pdf->ezText("\n", 10);
				$pdf_doc->pdf->ezText("Fecha/Hora impresión: ".Date('d-m-Y - H:i:s'), 10, array('justification' => 'left'));
				$pdf_doc->pdf->ezText("\n", 10);
                  $pdf_doc->new_table();
                  $pdf_doc->add_table_row(
                     array(
                         'subcuenta' => "<b>Subcuenta: </b>".$subc->codsubcuenta,
						 'descripcion' =>$subc->descripcion,
                         'alias' => "<b>Alias: </b>".$subc->alias,
						 'periodo' => "<b>Periodo: </b>".$subc->get_nom_mes($mes).'  '.$subc->codejercicio,
                     )
                  );
                  $pdf_doc->save_table(
                     array(
                         'cols' => array(
                             'campos' => array('justification' => 'right', 'width' => 70),
                             'factura' => array('justification' => 'left')
                         ),
                         'showLines' => 0,
                         'width' => 540
                     )
                  );
                  $pdf_doc->pdf->ezText("\n", 10);
                  
                  
                  /// Creamos la tabla con las lineas
                  $pdf_doc->new_table();
				  $pdf_doc->add_table_row(
                     array(
                         'asiento' => "",
						 'fecha' =>"",
                         'concepto' => "Saldo Anterior",
						 'debe' => "",
						 'haber' => "",
						 'saldo' => $subc->get_partidas_saldo_anterior_ver($mes),
                     )
                  );
                  $pdf_doc->add_table_header(
                     array(
                         'asiento' => '<b>Asiento</b>',
                         'fecha' => '<b>Fecha</b>',
                         'concepto' => '<b>Concepto</b>',
                         'debe' => '<b>Debe</b>',
                         'haber' => '<b>Haber</b>',
                         'saldo' => '<b>Saldo</b>'
                     )
                  );
                  for($i = $linea_actual; (($linea_actual < ($lppag + $i)) AND ($linea_actual < $lineasfact));)
                  {
                     $pdf_doc->add_table_row(
                        array(
                            'asiento' => $partidas[$linea_actual]->numero,
                            'fecha' => $partidas[$linea_actual]->fecha,
                            'concepto' => substr($partidas[$linea_actual]->concepto, 0, 60),
                            'debe' => $this->show_numero($partidas[$linea_actual]->debe),
                            'haber' => $this->show_numero($partidas[$linea_actual]->haber),
                            'saldo' => $this->show_numero($partidas[$linea_actual]->saldo)
                        )
                     );
					 $a=$linea_actual;
                     $linea_actual++;
                  }
                  /// añadimos las sumas de la línea actual
                  $pdf_doc->add_table_row(
                        array(
                            'asiento' => '',
                            'fecha' => '',
                            'concepto' => '',
                            'debe' => '<b>'.$this->show_numero($partidas[$linea_actual-1]->sum_debe).'</b>',
                            'haber' => '<b>'.$this->show_numero($partidas[$linea_actual-1]->sum_haber).'</b>',
                            'saldo' => ''
                        )
                  );
                  $pdf_doc->save_table(
                     array(
                         'fontSize' => 8,
                         'cols' => array(
                             'debe' => array('justification' => 'right'),
                             'haber' => array('justification' => 'right'),
                             'saldo' => array('justification' => 'right')
                         ),
                         'width' => 540,
                         'shaded' => 0
                     )
                  );
               }
            }

			$pdf_doc->pdf->ezText("\n", 18);
				$pdf_doc->pdf->ezText("<b>Total Debe :    </b>".$this->show_numero($partidas[$linea_actual-1]->sum_debe), 12, array('justification' => 'left'));
				$pdf_doc->pdf->ezText("\n", 12);
				$pdf_doc->pdf->ezText("<b>Total Haber :   </b>".$this->show_numero($partidas[$linea_actual-1]->sum_haber), 12, array('justification' => 'left'));
				$pdf_doc->pdf->ezText("\n", 12);
				$pdf_doc->pdf->ezText("<b>Saldo :         </b>".$this->show_numero($partidas[$linea_actual-1]->saldo), 12, array('justification' => 'left'));
            
			$pdf_doc->show();
  //          $pdf_doc->save('tmp/'.FS_TMP_NAME.'libro_mayor/'.$subc->idsubcuenta.'-'.$mes.'-'.$subc->codejercicio.'.pdf');
  //       }
      }
   }
   
   public function libro_mayor_archivo(&$subc,$mes, $echos = FALSE)
   {
      if($subc)
      {
         if( !file_exists('tmp/'.FS_TMP_NAME.'libro_mayor') )
            mkdir('tmp/'.FS_TMP_NAME.'libro_mayor');
         
         if( !file_exists('tmp/'.FS_TMP_NAME.'libro_mayor/'.$subc->idsubcuenta.'-'.$mes.'-'.$subc->codejercicio.'.pdf') )
         {
            if($echos)
               echo '.';
            
            $pdf_doc = new fs_pdf();
            $pdf_doc->pdf->addInfo('Title', 'Libro mayor de ' . $subc->codsubcuenta);
            $pdf_doc->pdf->addInfo('Subject', 'Libro mayor de ' . $subc->codsubcuenta);
            $pdf_doc->pdf->addInfo('Author', $this->empresa->nombre);
            $pdf_doc->pdf->ezStartPageNumbers(590, 10, 10, 'left', '{PAGENUM} de {TOTALPAGENUM}');
            
            $partidas = $subc->get_partidas_libros($mes,$offset=0);
            if($partidas)
            {
               $lineasfact = count($partidas);
               $linea_actual = 0;
               $lppag = 49;
               
               // Imprimimos las páginas necesarias
               while($linea_actual < $lineasfact)
               {
                  /// salto de página
                  if($linea_actual > 0)
                     $pdf_doc->pdf->ezNewPage();
					 /////  Primer encabezado
				$pdf_doc->pdf->ezText("<b>".$this->empresa->nombre."</b>", 14, array('justification' => 'left'));
				$pdf_doc->pdf->ezText("<b>Libro Mayor</b>", 12, array('justification' => 'center'));
				$pdf_doc->pdf->ezText("\n", 10);
				$pdf_doc->pdf->ezText("Fecha/Hora impresión: ".Date('d-m-Y - H:i:s'), 10, array('justification' => 'left'));
				$pdf_doc->pdf->ezText("\n", 10);
                  $pdf_doc->new_table();
                  $pdf_doc->add_table_row(
                     array(
                         'subcuenta' => "<b>Subcuenta: </b>".$subc->codsubcuenta,
						 'descripcion' =>$subc->descripcion,
                         'alias' => "<b>Alias: </b>".$subc->alias,
						 'periodo' => "<b>Periodo: </b>".$subc->get_nom_mes($mes).'  '.$subc->codejercicio,
                     )
                  );
                  $pdf_doc->save_table(
                     array(
                         'cols' => array(
                             'campos' => array('justification' => 'right', 'width' => 70),
                             'factura' => array('justification' => 'left')
                         ),
                         'showLines' => 0,
                         'width' => 540
                     )
                  );
                  $pdf_doc->pdf->ezText("\n", 10);
                  
                  
                  /// Creamos la tabla con las lineas
                  $pdf_doc->new_table();
				  $pdf_doc->add_table_row(
                     array(
                         'asiento' => "",
						 'fecha' =>"",
                         'concepto' => "Saldo Anterior",
						 'debe' => "",
						 'haber' => "",
						 'saldo' => $subc->get_partidas_saldo_anterior(),
                     )
                  );
                  $pdf_doc->add_table_header(
                     array(
                         'asiento' => '<b>Asiento</b>',
                         'fecha' => '<b>Fecha</b>',
                         'concepto' => '<b>Concepto</b>',
                         'debe' => '<b>Debe</b>',
                         'haber' => '<b>Haber</b>',
                         'saldo' => '<b>Saldo</b>'
                     )
                  );
                  for($i = $linea_actual; (($linea_actual < ($lppag + $i)) AND ($linea_actual < $lineasfact));)
                  {
                     $pdf_doc->add_table_row(
                        array(
                            'asiento' => $partidas[$linea_actual]->numero,
                            'fecha' => $partidas[$linea_actual]->fecha,
                            'concepto' => substr($partidas[$linea_actual]->concepto, 0, 60),
                            'debe' => $this->show_numero($partidas[$linea_actual]->debe),
                            'haber' => $this->show_numero($partidas[$linea_actual]->haber),
                            'saldo' => $this->show_numero($partidas[$linea_actual]->saldo)
                        )
                     );
					 $a=$linea_actual;
                     $linea_actual++;
                  }
                  /// añadimos las sumas de la línea actual
                  $pdf_doc->add_table_row(
                        array(
                            'asiento' => '',
                            'fecha' => '',
                            'concepto' => '',
                            'debe' => '<b>'.$this->show_numero($partidas[$linea_actual-1]->sum_debe).'</b>',
                            'haber' => '<b>'.$this->show_numero($partidas[$linea_actual-1]->sum_haber).'</b>',
                            'saldo' => ''
                        )
                  );
                  $pdf_doc->save_table(
                     array(
                         'fontSize' => 8,
                         'cols' => array(
                             'debe' => array('justification' => 'right'),
                             'haber' => array('justification' => 'right'),
                             'saldo' => array('justification' => 'right')
                         ),
                         'width' => 540,
                         'shaded' => 0
                     )
                  );
               }
            }

			$pdf_doc->pdf->ezText("\n", 18);
				$pdf_doc->pdf->ezText("<b>Total Debe :    </b>".$this->show_numero($partidas[$linea_actual-1]->sum_debe), 12, array('justification' => 'left'));
				$pdf_doc->pdf->ezText("\n", 12);
				$pdf_doc->pdf->ezText("<b>Total Haber :   </b>".$this->show_numero($partidas[$linea_actual-1]->sum_haber), 12, array('justification' => 'left'));
				$pdf_doc->pdf->ezText("\n", 12);
				$pdf_doc->pdf->ezText("<b>Saldo :         </b>".$this->show_numero($partidas[$linea_actual-1]->saldo), 12, array('justification' => 'left'));
            
            $pdf_doc->save('tmp/'.FS_TMP_NAME.'libro_mayor/'.$subc->idsubcuenta.'-'.$mes.'-'.$subc->codejercicio.'.pdf');
         }
      }
   }
   
   private function libro_diario(&$eje)
   {
      if($eje)
      {
         if( !file_exists('tmp/'.FS_TMP_NAME.'libro_diario') )
            mkdir('tmp/'.FS_TMP_NAME.'libro_diario');
         
         if( !file_exists('tmp/'.FS_TMP_NAME.'libro_diario/'.$eje->codejercicio.'.pdf') )
         {
            echo ' '.$eje->codejercicio;
            
            $pdf_doc = new fs_pdf('a4', 'landscape', 'Courier');
            $pdf_doc->pdf->addInfo('Title', 'Libro diario de ' . $eje->codejercicio);
            $pdf_doc->pdf->addInfo('Subject', 'Libro mayor de ' . $eje->codejercicio);
            $pdf_doc->pdf->addInfo('Author', $this->empresa->nombre);
            $pdf_doc->pdf->ezStartPageNumbers(800, 10, 10, 'left', '{PAGENUM} de {TOTALPAGENUM}');
            
            $partida = new partida();
            $sum_debe = 0;
            $sum_haber = 0;
            
            /// leemos todas las partidas del ejercicio
            $lppag = 33;
            $lactual = 0;
            $lineas = $partida->full_from_ejercicio($eje->codejercicio, $lactual, $lppag);
            while( count($lineas) > 0 )
            {
               if($lactual > 0)
               {
                  $pdf_doc->pdf->ezNewPage();
                  echo '+';
               }
               
               $pdf_doc->pdf->ezText($this->empresa->nombre." - libro diario ".$eje->year()."\n\n", 12);
               
               /// Creamos la tabla con las lineas
               $pdf_doc->new_table();
               $pdf_doc->add_table_header(
                  array(
                      'asiento' => '<b>Asiento</b>',
                      'fecha' => '<b>Fecha</b>',
                      'subcuenta' => '<b>Subcuenta</b>',
                      'concepto' => '<b>Concepto</b>',
                      'debe' => '<b>Debe</b>',
                      'haber' => '<b>Haber</b>'
                  )
               );
               
               foreach($lineas as $linea)
               {
                  $pdf_doc->add_table_row(
                     array(
                         'asiento' => $linea['numero'],
                         'fecha' => $linea['fecha'],
                         'subcuenta' => $linea['codsubcuenta'].' '.substr($linea['descripcion'], 0, 35),
                         'concepto' => substr($linea['concepto'], 0, 45),
                         'debe' => $this->show_numero($linea['debe']),
                         'haber' => $this->show_numero($linea['haber'])
                     )
                  );
                  
                  $sum_debe += floatval($linea['debe']);
                  $sum_haber += floatval($linea['haber']);
                  $lactual++;
               }
               
               /// añadimos las sumas de la línea actual
               $pdf_doc->add_table_row(
                  array(
                      'asiento' => '',
                      'fecha' => '',
                      'subcuenta' => '',
                      'concepto' => '',
                      'debe' => '<b>'.$this->show_numero($sum_debe).'</b>',
                      'haber' => '<b>'.$this->show_numero($sum_haber).'</b>'
                  )
               );
               $pdf_doc->save_table(
                  array(
                      'fontSize' => 9,
                      'cols' => array(
                          'debe' => array('justification' => 'right'),
                          'haber' => array('justification' => 'right')
                      ),
                      'width' => 780,
                      'shaded' => 0
                  )
               );
               
               $lineas = $partida->full_from_ejercicio($eje->codejercicio, $lactual, $lppag);
            }
            
            $pdf_doc->save('tmp/'.FS_TMP_NAME.'libro_diario/'.$eje->codejercicio.'.pdf');
         }
      }
   }
   
   private function show_numero($num)
   {
      return number_format($num, FS_NF0, FS_NF1, FS_NF2);
   }
}
