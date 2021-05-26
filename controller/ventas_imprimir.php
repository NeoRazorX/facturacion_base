<?php
/*
 * This file is part of facturacion_base
 * Copyright (C) 2014-2021    Carlos Garcia Gomez     neorazorx@gmail.com
 * Copyright (C) 2017         Francesc Pineda Segarra shawe.ewahs@gmail.com
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

require_once __DIR__ . '/compras_imprimir.php';

/**
 * Esta clase agrupa los procedimientos de imprimir/enviar albaranes y facturas.
 */
class ventas_imprimir extends compras_imprimir
{

    public $cliente;

    public function __construct($name = __CLASS__, $title = 'imprimir', $folder = 'ventas')
    {
        parent::__construct($name, $title, $folder);
    }

    protected function init()
    {
        parent::init();
        $this->cliente = FALSE;
    }

    protected function private_core()
    {
        $this->init();

        if (isset($_REQUEST['albaran']) && isset($_REQUEST['id'])) {
            $alb = new albaran_cliente();
            $this->documento = $alb->get($_REQUEST['id']);
            if ($this->documento) {
                $cliente = new cliente();
                $this->cliente = $cliente->get($this->documento->codcliente);
            }

            if (isset($_POST['email'])) {
                $this->enviar_email('albaran');
            } else {
                $this->generar_pdf_albaran();
            }
        } else if (isset($_REQUEST['factura']) && isset($_REQUEST['id'])) {
            $fac = new factura_cliente();
            $this->documento = $fac->get($_REQUEST['id']);
            if ($this->documento) {
                $cliente = new cliente();
                $this->cliente = $cliente->get($this->documento->codcliente);
            }

            if (isset($_POST['email'])) {
                $this->enviar_email('factura', $_REQUEST['tipo']);
            } else {
                $this->generar_pdf_factura($_REQUEST['tipo']);
            }
        }
    }

    protected function share_extensions()
    {
        $extensiones = array(
            array(
                'name' => 'imprimir_albaran',
                'page_from' => __CLASS__,
                'page_to' => 'ventas_albaran',
                'type' => 'pdf',
                'text' => '<span class="glyphicon glyphicon-print"></span>&nbsp; ' . ucfirst(FS_ALBARAN) . ' simple',
                'params' => '&albaran=TRUE'
            ),
            array(
                'name' => 'imprimir_albaran_noval',
                'page_from' => __CLASS__,
                'page_to' => 'ventas_albaran',
                'type' => 'pdf',
                'text' => '<span class="glyphicon glyphicon-print"></span>&nbsp; ' . ucfirst(FS_ALBARAN) . ' sin valorar',
                'params' => '&albaran=TRUE&noval=TRUE'
            ),
            array(
                'name' => 'email_albaran',
                'page_from' => __CLASS__,
                'page_to' => 'ventas_albaran',
                'type' => 'email',
                'text' => ucfirst(FS_ALBARAN) . ' simple',
                'params' => '&albaran=TRUE'
            ),
            array(
                'name' => 'imprimir_factura',
                'page_from' => __CLASS__,
                'page_to' => 'ventas_factura',
                'type' => 'pdf',
                'text' => '<span class="glyphicon glyphicon-print"></span>&nbsp; ' . ucfirst(FS_FACTURA) . ' simple',
                'params' => '&factura=TRUE&tipo=simple'
            ),
            array(
                'name' => 'imprimir_factura_carta',
                'page_from' => __CLASS__,
                'page_to' => 'ventas_factura',
                'type' => 'pdf',
                'text' => '<span class="glyphicon glyphicon-print"></span>&nbsp; Modelo carta',
                'params' => '&factura=TRUE&tipo=carta'
            ),
            array(
                'name' => 'email_factura',
                'page_from' => __CLASS__,
                'page_to' => 'ventas_factura',
                'type' => 'email',
                'text' => ucfirst(FS_FACTURA) . ' simple',
                'params' => '&factura=TRUE&tipo=simple'
            )
        );
        foreach ($extensiones as $ext) {
            $fsext = new fs_extension($ext);
            if (!$fsext->save()) {
                $this->new_error_msg('Error al guardar la extensión ' . $ext['name']);
            }
        }
    }

    protected function generar_pdf_lineas_venta(&$pdf_doc, &$lineas, &$linea_actual, &$lppag)
    {
        /// calculamos el número de páginas
        if (!isset($this->numpaginas)) {
            $this->numpaginas = 1;
            $linea_a = 0;
            $lineas_size = 0;
            while ($linea_a < count($lineas)) {
                $lineas_size += $this->get_linea_size($lineas[$linea_a]->referencia . ' ' . $lineas[$linea_a]->descripcion);
                if ($lineas_size > $lppag) {
                    $this->numpaginas++;
                    $lineas_size = $this->get_linea_size($lineas[$linea_a]->referencia . ' ' . $lineas[$linea_a]->descripcion);
                }

                $linea_a++;
            }
        }

        /// leemos las líneas para ver si hay que mostrar los tipos de iva, re o irpf
        $lineas_size = 0;
        $dec_cantidad = 0;
        $iva = $re = $irpf = FALSE;
        $multi_iva = $multi_re = $multi_irpf = FALSE;
        $this->impresion['print_dto'] = FALSE;
        for ($i = $linea_actual; $i < count($lineas) && $i < $linea_actual + $lppag; $i++) {
            /// comprobamos el número de decimales en la cantidad
            while ($dec_cantidad < 5 && $lineas[$i]->cantidad != round($lineas[$i]->cantidad, $dec_cantidad)) {
                $dec_cantidad++;
            }

            if ($lineas[$i]->dtopor != 0) {
                $this->impresion['print_dto'] = TRUE;
            }

            if ($iva === FALSE) {
                $iva = $lineas[$i]->iva;
            } else if ($lineas[$i]->iva != $iva) {
                $multi_iva = TRUE;
            }

            if ($re === FALSE) {
                $re = $lineas[$i]->recargo;
            } else if ($lineas[$i]->recargo != $re) {
                $multi_re = TRUE;
            }

            if ($irpf === FALSE) {
                $irpf = $lineas[$i]->irpf;
            } else if ($lineas[$i]->irpf != $irpf) {
                $multi_irpf = TRUE;
            }

            $lineas_size += $this->get_linea_size($lineas[$i]->referencia . ' ' . $lineas[$i]->descripcion);
            if ($lineas_size > $lppag) {
                $lppag = $i - $linea_actual;
            }
        }

        /*
         * Creamos la tabla con las lineas del documento
         */
        $pdf_doc->new_table();
        $table_header = array(
            'alb' => '<b>' . ucfirst(FS_ALBARAN) . '</b>',
            'descripcion' => '<b>Ref. + Descripción</b>',
            'cantidad' => '<b>Cant.</b>',
            'pvp' => '<b>Precio</b>',
        );

        /// ¿Desactivamos la columna de albaran?
        if (get_class_name($this->documento) == 'factura_cliente') {
            if ($this->impresion['print_alb']) {
                /// aunque esté activada, si la factura no viene de un albaran, la desactivamos
                $this->impresion['print_alb'] = FALSE;
                foreach ($lineas as $lin) {
                    if ($lin->idalbaran) {
                        $this->impresion['print_alb'] = TRUE;
                        break;
                    }
                }
            }

            if (!$this->impresion['print_alb']) {
                unset($table_header['alb']);
            }
        } else {
            unset($table_header['alb']);
        }

        if ($this->impresion['print_dto'] && !isset($_GET['noval'])) {
            $table_header['dto'] = '<b>Dto.</b>';
        }

        if ($multi_iva && !isset($_GET['noval'])) {
            $table_header['iva'] = '<b>' . FS_IVA . '</b>';
        }

        if ($multi_re && !isset($_GET['noval'])) {
            $table_header['re'] = '<b>R.E.</b>';
        }

        if ($multi_irpf && !isset($_GET['noval'])) {
            $table_header['irpf'] = '<b>' . FS_IRPF . '</b>';
        }

        if (isset($_GET['noval'])) {
            unset($table_header['pvp']);
        } else {
            $table_header['importe'] = '<b>Importe</b>';
        }

        $pdf_doc->add_table_header($table_header);

        for ($i = $linea_actual; (($linea_actual < ($lppag + $i)) && ( $linea_actual < count($lineas)));) {
            $descripcion = fs_fix_html($lineas[$linea_actual]->descripcion);
            if (!is_null($lineas[$linea_actual]->referencia) && $this->impresion['print_ref']) {
                $descripcion = '<b>' . $lineas[$linea_actual]->referencia . '</b> ' . $descripcion;
            }

            /// ¿El articulo tiene trazabilidad?
            $descripcion .= $this->generar_trazabilidad($lineas[$linea_actual]);

            $due_lineas = $this->fbase_calc_desc_due([$lineas[$linea_actual]->dtopor, $lineas[$linea_actual]->dtopor2, $lineas[$linea_actual]->dtopor3, $lineas[$linea_actual]->dtopor4]);

            $fila = array(
                'alb' => '-',
                'cantidad' => $this->show_numero($lineas[$linea_actual]->cantidad, $dec_cantidad),
                'descripcion' => $descripcion,
                'pvp' => $this->show_precio($lineas[$linea_actual]->pvpunitario, $this->documento->coddivisa, TRUE, FS_NF0_ART),
                'dto' => $this->show_numero($due_lineas) . " %",
                'iva' => $this->show_numero($lineas[$linea_actual]->iva) . " %",
                're' => $this->show_numero($lineas[$linea_actual]->recargo) . " %",
                'irpf' => $this->show_numero($lineas[$linea_actual]->irpf) . " %",
                'importe' => $this->show_precio($lineas[$linea_actual]->pvptotal, $this->documento->coddivisa)
            );

            if ($lineas[$linea_actual]->dtopor == 0) {
                $fila['dto'] = '';
            }

            if ($lineas[$linea_actual]->recargo == 0) {
                $fila['re'] = '';
            }

            if ($lineas[$linea_actual]->irpf == 0) {
                $fila['irpf'] = '';
            }

            if (!$lineas[$linea_actual]->mostrar_cantidad) {
                $fila['cantidad'] = '';
            }

            if (!$lineas[$linea_actual]->mostrar_precio) {
                $fila['pvp'] = '';
                $fila['dto'] = '';
                $fila['iva'] = '';
                $fila['re'] = '';
                $fila['irpf'] = '';
                $fila['importe'] = '';
            }

            if (get_class_name($lineas[$linea_actual]) == 'linea_factura_cliente' && $this->impresion['print_alb']) {
                $fila['alb'] = $lineas[$linea_actual]->albaran_numero();
            }

            $pdf_doc->add_table_row($fila);
            $linea_actual++;
        }

        $pdf_doc->save_table(
            array(
                'fontSize' => 8,
                'cols' => array(
                    'cantidad' => array('justification' => 'right'),
                    'pvp' => array('justification' => 'right'),
                    'dto' => array('justification' => 'right'),
                    'iva' => array('justification' => 'right'),
                    're' => array('justification' => 'right'),
                    'irpf' => array('justification' => 'right'),
                    'importe' => array('justification' => 'right')
                ),
                'width' => 520,
                'shaded' => 1,
                'shadeCol' => array(0.95, 0.95, 0.95),
                'lineCol' => array(0.3, 0.3, 0.3),
            )
        );

        /// ¿Última página?
        if ($linea_actual == count($lineas) && $this->documento->observaciones != '') {
            $pdf_doc->pdf->ezText("\n" . fs_fix_html($this->documento->observaciones), 9);
        }
    }

    /**
     * Devuelve el texto con los números de serie o lotes de la $linea
     * @param linea_albaran_compra $linea
     * @return string
     */
    protected function generar_trazabilidad($linea, $tabla1 = 'linea_albaran_cliente', $columna1 = 'idlalbventa', $tabla2 = 'linea_factura_cliente', $columna2 = 'idlfacventa')
    {
        return parent::generar_trazabilidad($linea, $tabla1, $columna1, $tabla2, $columna2);
    }

    protected function generar_pdf_datos_cliente(&$pdf_doc, &$lppag)
    {
        $tipo_doc = ucfirst(FS_ALBARAN);
        $width_campo1 = 110;
        $rectificativa = FALSE;
        if (get_class_name($this->documento) == 'factura_cliente') {
            if ($this->documento->idfacturarect) {
                $tipo_doc = ucfirst(FS_FACTURA_RECTIFICATIVA);
                $rectificativa = TRUE;
            } else {
                $tipo_doc = ucfirst(FS_FACTURA);
            }
        } else if (get_class_name($this->documento) == 'pedido_cliente') {
            $tipo_doc = ucfirst(FS_PEDIDO);
        } else if (get_class_name($this->documento) == 'presupuesto_cliente') {
            $tipo_doc = ucfirst(FS_PRESUPUESTO);
        }

        $tipoidfiscal = FS_CIFNIF;
        if ($this->cliente) {
            $tipoidfiscal = $this->cliente->tipoidfiscal;
        }

        /*
         * Esta es la tabla con los datos del cliente:
         * Albarán:                 Fecha:
         * Cliente:               CIF/NIF:
         * Dirección:           Teléfonos:
         */
        $pdf_doc->new_table();
        $pdf_doc->add_table_row(
            array(
                'campo1' => "<b>" . $tipo_doc . ":</b>",
                'dato1' => $this->documento->codigo,
                'campo2' => "<b>Fecha:</b> " . $this->documento->fecha
            )
        );

        if ($rectificativa) {
            $pdf_doc->add_table_row(
                array(
                    'campo1' => "<b>Original:</b>",
                    'dato1' => $this->documento->codigorect,
                    'campo2' => '',
                )
            );
        }

        $pdf_doc->add_table_row(
            array(
                'campo1' => "<b>Cliente:</b> ",
                'dato1' => fs_fix_html($this->documento->nombrecliente),
                'campo2' => "<b>" . $tipoidfiscal . ":</b> " . $this->documento->cifnif
            )
        );

        $direccion = $this->documento->direccion;
        if ($this->documento->apartado) {
            $direccion .= ' - ' . ucfirst(FS_APARTADO) . ': ' . $this->documento->apartado;
        }
        if ($this->documento->codpostal) {
            $direccion .= ' - CP: ' . $this->documento->codpostal;
        }
        $direccion .= ' - ' . $this->documento->ciudad;
        if ($this->documento->provincia) {
            $direccion .= ' (' . $this->documento->provincia . ')';
        }
        if ($this->documento->codpais != $this->empresa->codpais) {
            $pais0 = new pais();
            $pais = $pais0->get($this->documento->codpais);
            if ($pais) {
                $direccion .= ' ' . $pais->nombre;
            }
        }
        $row = array(
            'campo1' => "<b>Dirección:</b>",
            'dato1' => fs_fix_html($direccion),
            'campo2' => ''
        );

        if (!$this->cliente) {
            /// nada
        } else if ($this->cliente->telefono1) {
            $row['campo2'] = "<b>Teléfonos:</b> " . $this->cliente->telefono1;
            if ($this->cliente->telefono2) {
                $row['campo2'] .= "\n" . $this->cliente->telefono2;
                $lppag -= 2;
            }
        } else if ($this->cliente->telefono2) {
            $row['campo2'] = "<b>Teléfonos:</b> " . $this->cliente->telefono2;
        }
        $pdf_doc->add_table_row($row);

        /* Si tenemos dirección de envío y es diferente a la de facturación */
        if ($this->documento->envio_direccion && $this->documento->direccion != $this->documento->envio_direccion) {
            $direccionenv = '';
            if ($this->documento->envio_codigo) {
                $direccionenv .= 'Cod. Seg.: "' . $this->documento->envio_codigo . '" - ';
            }
            if ($this->documento->envio_nombre) {
                $direccionenv .= $this->documento->envio_nombre . ' ' . $this->documento->envio_apellidos . ' - ';
            }
            $direccionenv .= $this->documento->envio_direccion;
            if ($this->documento->envio_apartado) {
                $direccionenv .= ' - ' . ucfirst(FS_APARTADO) . ': ' . $this->documento->envio_apartado;
            }
            if ($this->documento->envio_codpostal) {
                $direccionenv .= ' - CP: ' . $this->documento->envio_codpostal;
            }
            $direccionenv .= ' - ' . $this->documento->envio_ciudad;
            if ($this->documento->envio_provincia) {
                $direccionenv .= ' (' . $this->documento->envio_provincia . ')';
            }
            if ($this->documento->envio_codpais != $this->empresa->codpais) {
                $pais0 = new pais();
                $pais = $pais0->get($this->documento->envio_codpais);
                if ($pais) {
                    $direccionenv .= ' ' . $pais->nombre;
                }
            }
            /* Tal y como está la plantilla actualmente:
             * Cada 54 caracteres es una línea en la dirección y no sabemos cuantas líneas tendrá,
             * a partir de ahí es una linea a restar por cada 54 caracteres
             */
            $lppag -= ceil(strlen($direccionenv) / 54);
            $row_dir_env = array(
                'campo1' => "<b>Enviar a:</b>",
                'dato1' => fs_fix_html($direccionenv),
                'campo2' => ''
            );
            $pdf_doc->add_table_row($row_dir_env);
        }

        if ($this->empresa->codpais != 'ESP') {
            $pdf_doc->add_table_row(
                array(
                    'campo1' => "<b>Régimen " . FS_IVA . ":</b> ",
                    'dato1' => $this->cliente->regimeniva,
                    'campo2' => ''
                )
            );
        }

        $pdf_doc->save_table(
            array(
                'cols' => array(
                    'campo1' => array('width' => $width_campo1, 'justification' => 'right'),
                    'dato1' => array('justification' => 'left'),
                    'campo2' => array('justification' => 'right')
                ),
                'showLines' => 0,
                'width' => 520,
                'shaded' => 0
            )
        );
        $pdf_doc->pdf->ezText("\n", 10);
    }

    protected function generar_pdf_totales(&$pdf_doc, &$lineas_iva, $pagina)
    {
        if (isset($_GET['noval'])) {
            $pdf_doc->pdf->addText(10, 10, 8, $pdf_doc->center_text('Página ' . $pagina . '/' . $this->numpaginas, 250));
        } else {
            parent::generar_pdf_totales($pdf_doc, $lineas_iva, $pagina);
        }
    }

    public function generar_pdf_presupuesto($archivo = FALSE)
    {
        
    }

    public function generar_pdf_pedido($archivo = FALSE)
    {
        
    }

    public function generar_pdf_albaran($archivo = FALSE)
    {
        if (!$archivo) {
            /// desactivamos la plantilla HTML
            $this->template = FALSE;
        }

        /// Creamos el PDF y escribimos sus metadatos
        $pdf_doc = new fs_pdf();
        $pdf_doc->pdf->addInfo('Title', ucfirst(FS_ALBARAN) . ' ' . $this->documento->codigo);
        $pdf_doc->pdf->addInfo('Subject', ucfirst(FS_ALBARAN) . ' de cliente ' . $this->documento->codigo);
        $pdf_doc->pdf->addInfo('Author', $this->empresa->nombre);

        $lineas = $this->documento->get_lineas();
        $lineas_iva = $pdf_doc->get_lineas_iva($lineas);
        if ($lineas) {
            $linea_actual = 0;
            $pagina = 1;

            /// imprimimos las páginas necesarias
            while ($linea_actual < count($lineas)) {
                $lppag = 35;

                /// salto de página
                if ($linea_actual > 0) {
                    $pdf_doc->pdf->ezNewPage();
                }

                $pdf_doc->generar_pdf_cabecera($this->empresa, $lppag);
                $this->generar_pdf_datos_cliente($pdf_doc, $lppag);
                $this->generar_pdf_lineas_venta($pdf_doc, $lineas, $linea_actual, $lppag);

                $pdf_doc->set_y(80);
                $this->generar_pdf_totales($pdf_doc, $lineas_iva, $pagina);
                $pagina++;
            }
        } else {
            $pdf_doc->pdf->ezText('¡' . ucfirst(FS_ALBARAN) . ' sin líneas!', 20);
        }

        if ($archivo) {
            if (!file_exists('tmp/' . FS_TMP_NAME . 'enviar')) {
                mkdir('tmp/' . FS_TMP_NAME . 'enviar');
            }

            $pdf_doc->save('tmp/' . FS_TMP_NAME . 'enviar/' . $archivo);
        } else {
            $pdf_doc->show(FS_ALBARAN . '_' . $this->documento->codigo . '.pdf');
        }
    }

    public function generar_pdf_factura($tipo = 'simple', $archivo = FALSE)
    {
        if (!$archivo) {
            /// desactivamos la plantilla HTML
            $this->template = FALSE;
        }

        /// Creamos el PDF y escribimos sus metadatos
        $pdf_doc = new fs_pdf();
        $pdf_doc->pdf->addInfo('Title', ucfirst(FS_FACTURA) . ' ' . $this->documento->codigo);
        $pdf_doc->pdf->addInfo('Subject', ucfirst(FS_FACTURA) . ' ' . $this->documento->codigo);
        $pdf_doc->pdf->addInfo('Author', $this->empresa->nombre);

        $lineas = $this->documento->get_lineas();
        $lineas_iva = $pdf_doc->get_lineas_iva($lineas);
        if ($lineas) {
            $linea_actual = 0;
            $pagina = 1;

            /// imprimimos las páginas necesarias
            while ($linea_actual < count($lineas)) {
                $lppag = 35;

                /// salto de página
                if ($linea_actual > 0) {
                    $pdf_doc->pdf->ezNewPage();
                }

                /*
                 * Creamos la cabecera de la página, en este caso para el modelo carta
                 */
                if ($tipo == 'carta') {
                    $pdf_doc->generar_pdf_cabecera($this->empresa, $lppag);
                    $this->generar_pdf_carta($pdf_doc);
                } else { /// esta es la cabecera de la página para el modelo 'simple'
                    $pdf_doc->generar_pdf_cabecera($this->empresa, $lppag);
                    $this->generar_pdf_datos_cliente($pdf_doc, $lppag);
                }

                $this->generar_pdf_lineas_venta($pdf_doc, $lineas, $linea_actual, $lppag, $this->documento);

                if ($linea_actual == count($lineas) && !$this->documento->pagada && $this->impresion['print_formapago']) {
                    $this->generar_pdf_forma_pago($pdf_doc);
                }

                $pdf_doc->set_y(80);
                $this->generar_pdf_totales($pdf_doc, $lineas_iva, $pagina);

                /// pié de página para la factura
                if ($this->empresa->pie_factura) {
                    $pdf_doc->pdf->addText(10, 10, 8, $pdf_doc->center_text(fs_fix_html($this->empresa->pie_factura), 180));
                }

                $pagina++;
            }
        } else {
            $pdf_doc->pdf->ezText('¡' . ucfirst(FS_FACTURA) . ' sin líneas!', 20);
        }

        if ($archivo) {
            if (!file_exists('tmp/' . FS_TMP_NAME . 'enviar')) {
                mkdir('tmp/' . FS_TMP_NAME . 'enviar');
            }

            $pdf_doc->save('tmp/' . FS_TMP_NAME . 'enviar/' . $archivo);
        } else {
            $pdf_doc->show(FS_FACTURA . '_' . $this->documento->codigo . '.pdf');
        }
    }

    protected function generar_pdf_carta(&$pdf_doc)
    {
        $direccion = $this->documento->nombrecliente . "\n" . $this->documento->direccion;
        if ($this->documento->apartado) {
            $direccion .= "\n " . ucfirst(FS_APARTADO) . ": " . $this->documento->apartado;
        }

        if ($this->documento->codpostal) {
            $direccion .= "\n CP: " . $this->documento->codpostal . ' - ';
        } else {
            $direccion .= "\n";
        }
        $direccion .= $this->documento->ciudad . "\n(" . $this->documento->provincia . ")";
        if ($this->documento->codpais != $this->empresa->codpais) {
            $pais0 = new pais();
            $pais = $pais0->get($this->documento->codpais);
            if ($pais) {
                $direccion .= ' ' . $pais->nombre;
            }
        }
        $pdf_doc->new_table();
        $pdf_doc->add_table_row(
            array(
                'campos' => "<b>" . ucfirst(FS_FACTURA) . ":</b>\n<b>Fecha:</b>\n<b>" . $this->cliente->tipoidfiscal . ":</b>",
                'factura' => $this->documento->codigo . "\n" . $this->documento->fecha . "\n" . $this->documento->cifnif,
                'cliente' => fs_fix_html($direccion)
            )
        );
        $pdf_doc->save_table(
            array(
                'cols' => array(
                    'campos' => array('justification' => 'right', 'width' => 100),
                    'factura' => array('justification' => 'left'),
                    'cliente' => array('justification' => 'right')
                ),
                'showLines' => 0,
                'width' => 520
            )
        );
        $pdf_doc->pdf->ezText("\n\n\n", 14);
    }

    protected function generar_pdf_forma_pago(&$pdf_doc)
    {
        $fp0 = new forma_pago();
        $forma_pago = $fp0->get($this->documento->codpago);
        if ($forma_pago) {
            $texto_pago = "\n<b>Forma de pago</b>: " . $forma_pago->descripcion;

            if (!$forma_pago->imprimir) {
                /// nada
            } else if ($forma_pago->domiciliado) {
                $cbc0 = new cuenta_banco_cliente();
                $encontrada = FALSE;
                foreach ($cbc0->all_from_cliente($this->documento->codcliente) as $cbc) {
                    $texto_pago .= "\n<b>Domiciliado en</b>: ";
                    if ($cbc->iban) {
                        $texto_pago .= $cbc->iban(TRUE);
                    }

                    if ($cbc->swift) {
                        $texto_pago .= "\n<b>SWIFT/BIC</b>: " . $cbc->swift;
                    }
                    $encontrada = TRUE;
                    break;
                }
                if (!$encontrada) {
                    $texto_pago .= "\n<b>El cliente no tiene cuenta bancaria asignada.</b>";
                }
            } else if ($forma_pago->codcuenta) {
                $cb0 = new cuenta_banco();
                $cuenta_banco = $cb0->get($forma_pago->codcuenta);
                if ($cuenta_banco) {
                    if ($cuenta_banco->iban) {
                        $iban = $cuenta_banco->iban(TRUE);
                        $blocks = explode(' ', $iban);
                        $texto_pago .= "\n<b>IBAN</b>: " . $iban . ' (último bloque ' . end($blocks) . ')';
                    }

                    if ($cuenta_banco->swift) {
                        $texto_pago .= "\n<b>SWIFT o BIC</b>: " . $cuenta_banco->swift;
                    }
                }
            }

            if (isset($this->documento->vencimiento)) {
                $texto_pago .= "\n<b>Vencimiento</b>: " . $this->documento->vencimiento;
            }

            $pdf_doc->pdf->ezText($texto_pago, 9);
        }
    }

    public function enviar_email($doc, $tipo = 'simple')
    {
        if ($this->empresa->can_send_mail()) {
            if ($doc == 'factura') {
                $filename = 'factura_' . $this->documento->codigo . '.pdf';
                $this->generar_pdf_factura($tipo, $filename);
            } else if ($doc == 'albaran') {
                $filename = 'albaran_' . $this->documento->codigo . '.pdf';
                $this->generar_pdf_albaran($filename);
            } else if ($doc == 'presupuesto') {
                $filename = 'presupuesto_' . $this->documento->codigo . '.pdf';
                $this->generar_pdf_presupuesto($filename);
            } else {
                $filename = 'pedido_' . $this->documento->codigo . '.pdf';
                $this->generar_pdf_pedido($filename);
            }

            $razonsocial = $this->documento->nombrecliente;
            if ($this->cliente && $_POST['email'] != $this->cliente->email && isset($_POST['guardar'])) {
                $this->cliente->email = $_POST['email'];
                $this->cliente->save();
            }

            if (file_exists('tmp/' . FS_TMP_NAME . 'enviar/' . $filename)) {
                $mail = $this->empresa->new_mail();
                $mail->FromName = $this->user->get_agente_fullname();

                if ($_POST['de'] != $mail->From) {
                    $mail->addReplyTo($_POST['de'], $mail->FromName);
                }

                $mail->addAddress($_POST['email'], $razonsocial);
                if ($_POST['email_copia']) {
                    if (isset($_POST['cco'])) {
                        $mail->addBCC($_POST['email_copia'], $razonsocial);
                    } else {
                        $mail->addCC($_POST['email_copia'], $razonsocial);
                    }
                }

                if ($doc == 'factura') {
                    $mail->Subject = $this->empresa->nombre . ': Su factura ' . $this->documento->codigo;
                } else if ($doc == 'albaran') {
                    $mail->Subject = $this->empresa->nombre . ': Su ' . FS_ALBARAN . ' ' . $this->documento->codigo;
                } else if ($doc == 'presupuesto') {
                    $mail->Subject = $this->empresa->nombre . ': Su ' . FS_PRESUPUESTO . ' ' . $this->documento->codigo;
                } else {
                    $mail->Subject = $this->empresa->nombre . ': Su ' . FS_PEDIDO . ' ' . $this->documento->codigo;
                }

                if ($this->is_html($_POST['mensaje'])) {
                    $mail->AltBody = strip_tags($_POST['mensaje']);
                    $mail->msgHTML($_POST['mensaje']);
                    $mail->isHTML(TRUE);
                } else {
                    $mail->Body = $_POST['mensaje'];
                }

                $mail->addAttachment('tmp/' . FS_TMP_NAME . 'enviar/' . $filename);
                if (is_uploaded_file($_FILES['adjunto']['tmp_name'])) {
                    $mail->addAttachment($_FILES['adjunto']['tmp_name'], $_FILES['adjunto']['name']);
                }

                if ($this->empresa->mail_connect($mail) && $mail->send()) {
                    $this->new_message('Mensaje enviado correctamente.');

                    /// nos guardamos la fecha de envío
                    $this->documento->femail = $this->today();
                    $this->documento->save();

                    $this->empresa->save_mail($mail);
                } else {
                    $this->new_error_msg("Error al enviar el email: " . $mail->ErrorInfo);
                }

                unlink('tmp/' . FS_TMP_NAME . 'enviar/' . $filename);
            } else {
                $this->new_error_msg('Imposible generar el PDF.');
            }
        }
    }
}
