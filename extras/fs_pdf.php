<?php
/*
 * This file is part of facturacion_base
 * Copyright (C) 2013-2017  Carlos Garcia Gomez  neorazorx@gmail.com
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

require_once __DIR__ . '/ezpdf/Cezpdf.php';

/**
 * Permite la generación de PDFs algo más sencilla.
 */
class fs_pdf
{

    const LOGO_X = 35;
    const LOGO_Y = 740;

    /**
     * Ruta al logotipo de la empresa.
     * @var string|false 
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
        if (!file_exists('tmp/' . FS_TMP_NAME . 'pdf')) {
            mkdir('tmp/' . FS_TMP_NAME . 'pdf');
        }

        $this->cargar_logo();

        $this->pdf = new Cezpdf($paper, $orientation);
        $this->pdf->selectFont(__DIR__ . "/ezpdf/fonts/" . $font . ".afm");
        $this->pdf->addInfo('Creator', 'FacturaScripts');
        $this->pdf->addInfo('Producer', 'FacturaScripts');
    }

    /**
     * Vuelca el documento PDF en la salida estándar.
     * @param string $filename
     */
    public function show($filename = 'doc.pdf')
    {
        $this->pdf->ezStream(array('Content-Disposition' => $filename));
    }

    /**
     * Guarda el documento PDF en el archivo $filename
     * @param string $filename
     * @return boolean
     */
    public function save($filename)
    {
        if ($filename) {
            if (file_exists($filename)) {
                unlink($filename);
            }

            $file = fopen($filename, 'a');
            if ($file) {
                fwrite($file, $this->pdf->ezOutput());
                fclose($file);
                return TRUE;
            }

            return TRUE;
        }

        return FALSE;
    }

    /**
     * Devuelve la coordenada Y actual en el documento.
     * @return integer
     */
    public function get_y()
    {
        return $this->pdf->y;
    }

    /**
     * Establece la coordenada Y actual en el documento.
     * @param integer $value
     */
    public function set_y($value)
    {
        $this->pdf->ezSetY($value);
    }

    /**
     * Carga la ruta del logotipo de la empresa.
     */
    private function cargar_logo()
    {
        if (!file_exists(FS_MYDOCS . 'images')) {
            @mkdir(FS_MYDOCS . 'images', 0777, TRUE);
        }

        /**
         * Antes se guardaba el logo en el temporal.
         * Mala decisión, lo movemos.
         */
        if (file_exists('tmp/' . FS_TMP_NAME . 'logo.png')) {
            rename('tmp/' . FS_TMP_NAME . 'logo.png', FS_MYDOCS . 'images/logo.png');
        } else if (file_exists('tmp/' . FS_TMP_NAME . 'logo.jpg')) {
            rename('tmp/' . FS_TMP_NAME . 'logo.jpg', FS_MYDOCS . 'images/logo.jpg');
        }

        $this->logo = FALSE;
        if (file_exists(FS_MYDOCS . 'images/logo.png')) {
            $this->logo = FS_MYDOCS . 'images/logo.png';
        } else if (file_exists(FS_MYDOCS . 'images/logo.jpg')) {
            $this->logo = FS_MYDOCS . 'images/logo.jpg';
        }
    }

    /**
     * Añade la cabecera al PDF con el logotipo y los datos de la empresa.
     * @param empresa $empresa
     * @param int $lppag
     */
    public function generar_pdf_cabecera(&$empresa, &$lppag)
    {
        /// ¿Añadimos el logo?
        if ($this->logo !== FALSE) {
            if (function_exists('imagecreatefromstring')) {
                $lppag -= 2; /// si metemos el logo, caben menos líneas

                $tamanyo = $this->calcular_tamanyo_logo();
                if (substr(strtolower($this->logo), -4) == '.png') {
                    $this->pdf->addPngFromFile($this->logo, self::LOGO_X, self::LOGO_Y, $tamanyo[0], $tamanyo[1]);
                } else if (function_exists('imagepng')) {
                    /**
                     * La librería ezpdf tiene problemas al redimensionar jpegs,
                     * así que hacemos la conversión a png para evitar estos problemas.
                     */
                    if (imagepng(imagecreatefromstring(file_get_contents($this->logo)), FS_MYDOCS . 'images/logo.png')) {
                        $this->pdf->addPngFromFile(FS_MYDOCS . 'images/logo.png', self::LOGO_X, self::LOGO_Y, $tamanyo[0], $tamanyo[1]);
                    } else {
                        $this->pdf->addJpegFromFile($this->logo, self::LOGO_X, self::LOGO_Y, $tamanyo[0], $tamanyo[1]);
                    }
                } else {
                    $this->pdf->addJpegFromFile($this->logo, self::LOGO_X, self::LOGO_Y, $tamanyo[0], $tamanyo[1]);
                }

                $this->pdf->ez['rightMargin'] = 40;
                $this->pdf->ezText("<b>" . fs_fix_html($empresa->nombre) . "</b>", 12, array('justification' => 'right'));
                $this->pdf->ezText(FS_CIFNIF . ": " . $empresa->cifnif, 8, array('justification' => 'right'));

                $direccion = $empresa->direccion . "\n";
                if ($empresa->apartado) {
                    $direccion .= ucfirst(FS_APARTADO) . ': ' . $empresa->apartado . ' - ';
                }

                if ($empresa->codpostal) {
                    $direccion .= 'CP: ' . $empresa->codpostal . ' - ';
                }

                if ($empresa->ciudad) {
                    $direccion .= $empresa->ciudad . ' - ';
                }

                if ($empresa->provincia) {
                    $direccion .= '(' . $empresa->provincia . ')';
                }

                if ($empresa->telefono) {
                    $direccion .= "\nTeléfono: " . $empresa->telefono;
                }

                $this->pdf->ezText(fs_fix_html($direccion) . "\n", 9, array('justification' => 'right'));
                $this->set_y(self::LOGO_Y + 10);
            } else {
                die('ERROR: no se encuentra la función imagecreatefromstring(). '
                    . 'Y por tanto no se puede usar el logotipo en los documentos.');
            }
        } else {
            $this->pdf->ezText("<b>" . fs_fix_html($empresa->nombre) . "</b>", 16, array('justification' => 'center'));
            $this->pdf->ezText(FS_CIFNIF . ": " . $empresa->cifnif, 8, array('justification' => 'center'));

            $direccion = $empresa->direccion;
            if ($empresa->apartado) {
                $direccion .= ' - ' . ucfirst(FS_APARTADO) . ': ' . $empresa->apartado;
            }

            if ($empresa->codpostal) {
                $direccion .= ' - CP: ' . $empresa->codpostal;
            }

            if ($empresa->ciudad) {
                $direccion .= ' - ' . $empresa->ciudad;
            }

            if ($empresa->provincia) {
                $direccion .= ' (' . $empresa->provincia . ')';
            }

            if ($empresa->telefono) {
                $direccion .= ' - Teléfono: ' . $empresa->telefono;
            }

            $this->pdf->ezText(fs_fix_html($direccion), 9, array('justification' => 'center'));
        }
    }

    private function calcular_tamanyo_logo()
    {
        $tamanyo = $size = getimagesize($this->logo);
        if ($size[0] > 200) {
            $tamanyo[0] = 200;
            $tamanyo[1] = $tamanyo[1] * $tamanyo[0] / $size[0];
            $size[0] = $tamanyo[0];
            $size[1] = $tamanyo[1];
        }

        if ($size[1] > 80) {
            $tamanyo[1] = 80;
            $tamanyo[0] = $tamanyo[0] * $tamanyo[1] / $size[1];
        }

        return $tamanyo;
    }

    public function center_text($word = '', $tot_width = 140)
    {
        if (strlen($word) == $tot_width) {
            return $word;
        } else if (strlen($word) < $tot_width) {
            return $this->center_text2($word, $tot_width);
        } else {
            $result = '';
            $nword = '';
            foreach (explode(' ', $word) as $aux) {
                if ($nword == '') {
                    $nword = $aux;
                } else if (strlen($nword) + strlen($aux) + 1 <= $tot_width) {
                    $nword = $nword . ' ' . $aux;
                } else {
                    if ($result != '') {
                        $result .= "\n";
                    }
                    $result .= $this->center_text2($nword, $tot_width);
                    $nword = $aux;
                }
            }
            if ($nword != '') {
                if ($result != '') {
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
        for ($i = 0; $i < $number_of_spaces; $i++) {
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
        if (empty($this->table_header)) {
            foreach (array_keys($this->table_rows[0]) as $k) {
                $this->table_header[$k] = '';
            }
        }

        $this->pdf->ezTable($this->table_rows, $this->table_header, '', $options);
    }

    /**
     * Revierte los cambios producidos por fs_model::no_html()
     * @deprecated since version 2017.012
     * @param string $txt
     * @return string
     */
    public function fix_html($txt)
    {
        return fs_fix_html($txt);
    }

    public function get_lineas_iva($lineas)
    {
        $retorno = array();
        $lineasiva = array();

        foreach ($lineas as $lin) {
            if (isset($lineasiva[$lin->codimpuesto])) {
                if ($lin->recargo > $lineasiva[$lin->codimpuesto]['recargo']) {
                    $lineasiva[$lin->codimpuesto]['recargo'] = $lin->recargo;
                }

                $lineasiva[$lin->codimpuesto]['neto'] += $lin->pvptotal;
                $lineasiva[$lin->codimpuesto]['totaliva'] += ($lin->pvptotal * $lin->iva) / 100;
                $lineasiva[$lin->codimpuesto]['totalrecargo'] += ($lin->pvptotal * $lin->recargo) / 100;
                $lineasiva[$lin->codimpuesto]['totallinea'] = $lineasiva[$lin->codimpuesto]['neto'] + $lineasiva[$lin->codimpuesto]['totaliva'] + $lineasiva[$lin->codimpuesto]['totalrecargo'];
            } else {
                $lineasiva[$lin->codimpuesto] = array(
                    'codimpuesto' => $lin->codimpuesto,
                    'iva' => $lin->iva,
                    'recargo' => $lin->recargo,
                    'neto' => $lin->pvptotal,
                    'totaliva' => ($lin->pvptotal * $lin->iva) / 100,
                    'totalrecargo' => ($lin->pvptotal * $lin->recargo) / 100,
                    'totallinea' => 0
                );
                $lineasiva[$lin->codimpuesto]['totallinea'] = $lineasiva[$lin->codimpuesto]['neto'] + $lineasiva[$lin->codimpuesto]['totaliva'] + $lineasiva[$lin->codimpuesto]['totalrecargo'];
            }
        }

        foreach ($lineasiva as $lin) {
            $retorno[] = $lin;
        }

        return $retorno;
    }
}
