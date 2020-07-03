<?php
/**
 * This file is part of facturacion_base
 * Copyright (C) 2016-2019 Carlos Garcia Gomez <neorazorx@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Description of dashboard
 *
 * @author Carlos Garcia Gomez
 */
class dashboard extends fs_controller
{

    public $anterior;
    public $anyo;
    public $hide_backup_warning;
    public $mes;
    public $neto;
    public $noticias;
    public $trimestre;

    public function __construct()
    {
        parent::__construct(__CLASS__, 'Dashboard', 'informes', FALSE, TRUE, TRUE);
    }

    /**
     * 
     * @return bool
     */
    public function show_backup_warning()
    {
        return 'localhost' === $_SERVER['SERVER_NAME'] && $this->hide_backup_warning != 'true';
    }

    protected function private_core()
    {
        /// cargamos la configuración
        $fsvar = new fs_var();
        $this->hide_backup_warning = $fsvar->simple_get('hidebackupw');
        if (isset($_GET['hidebackupw'])) {
            $this->hide_backup_warning = 'true';
            $fsvar->simple_save('hidebackupw', $this->hide_backup_warning);
        } elseif (isset($_POST['anterior'])) {
            $this->anterior = $_POST['anterior'];
            $fsvar->simple_save('dashboard_anterior', $this->anterior);

            $this->neto = $_POST['neto'];
            $fsvar->simple_save('dashboard_neto', $this->neto);

            $this->new_message('Datos guardados correctamente.');
        } else {
            $this->anterior = $fsvar->simple_get('dashboard_anterior');
            if (!$this->anterior) {
                $this->anterior = 'periodo';
            }

            $this->neto = $fsvar->simple_get('dashboard_neto');
            if (!$this->neto) {
                $this->neto = 'FALSE';
            }
        }

        $this->mes = array(
            'desde' => date('1-m-Y'),
            'hasta' => date('d-m-Y'),
            'anterior' => '-1 month',
            'compras' => 0,
            'compras_neto' => 0,
            'compras_anterior' => 0,
            'compras_anterior_neto' => 0,
            'compras_mejora' => 0,
            'compras_albaranes_pte' => 0,
            'compras_pedidos_pte' => 0,
            'compras_sinpagar' => 0,
            'ventas' => 0,
            'ventas_neto' => 0,
            'ventas_anterior' => 0,
            'ventas_anterior_neto' => 0,
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
        if (intval(date('d')) <= 3) {
            /// en los primeros días del mes, mejor comparamos los datos del anterior
            $this->mes['desde'] = date('1-m-Y', strtotime('-1 month'));
            $this->mes['hasta'] = date('t-m-Y', strtotime('-1 month'));
        }
        if ($this->anterior == 'año') {
            $this->mes['anterior'] = '-1 year';
        }
        $this->calcula_periodo($this->mes, TRUE);

        $this->trimestre = array(
            'anterior' => '-3 months',
            'compras' => 0,
            'compras_neto' => 0,
            'compras_anterior' => 0,
            'compras_anterior_neto' => 0,
            'compras_mejora' => 0,
            'ventas' => 0,
            'ventas_neto' => 0,
            'ventas_anterior' => 0,
            'ventas_anterior_neto' => 0,
            'ventas_mejora' => 0,
            'impuestos' => 0,
            'impuestos_anterior' => 0,
            'impuestos_mejora' => 0,
            'beneficios' => 0,
            'beneficios_anterior' => 0,
            'beneficios_mejora' => 0,
        );

        /// ahora hay que calcular bien las fechas del trimestre
        switch (date('m')) {
            case '1':
                $this->trimestre['desde'] = date('01-10-Y', strtotime('-1 year'));
                $this->trimestre['hasta'] = date('31-12-Y', strtotime('-1 year'));
                break;

            case '2':
            case '3':
            case '4':
                $this->trimestre['desde'] = date('01-01-Y');
                $this->trimestre['hasta'] = date('31-03-Y');
                break;

            case '5':
            case '6':
            case '7':
                $this->trimestre['desde'] = date('01-04-Y');
                $this->trimestre['hasta'] = date('30-06-Y');
                break;

            case '8':
            case '9':
            case '10':
                $this->trimestre['desde'] = date('01-07-Y');
                $this->trimestre['hasta'] = date('30-09-Y');
                break;

            case '11':
            case '12':
                $this->trimestre['desde'] = date('01-10-Y');
                $this->trimestre['hasta'] = date('31-12-Y');
                break;
        }
        if ($this->anterior == 'año') {
            $this->trimestre['anterior'] = '-1 year';
        }
        $this->calcula_periodo($this->trimestre);

        $this->anyo = array(
            'desde' => date('1-1-Y'),
            'hasta' => date('d-m-Y'),
            'anterior' => '-1 year',
            'compras' => 0,
            'compras_neto' => 0,
            'compras_anterior' => 0,
            'compras_anterior_neto' => 0,
            'compras_mejora' => 0,
            'ventas' => 0,
            'ventas_neto' => 0,
            'ventas_anterior' => 0,
            'ventas_anterior_neto' => 0,
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

        if ((float) substr(phpversion(), 0, 3) < 5.4) {
            $this->new_advice('Estás usando una versión de PHP demasiado antigua. '
                . 'En próximas actualizaciones FacturaScripts necesitará PHP 5.4 o superor.');
        }
    }

    private function calcula_periodo(&$stats, $pendiente = FALSE)
    {
        $desde_anterior = date('d-m-Y', strtotime($stats['desde'] . ' ' . $stats['anterior']));
        $hasta_anterior = date('d-m-Y', strtotime($stats['hasta'] . ' ' . $stats['anterior']));
        if ($stats['anterior'] == '-3 months') {
            $hasta_anterior = date('t-m-Y', strtotime($stats['hasta'] . ' -1 day ' . $stats['anterior']));
        }

        if ($this->db->table_exists('facturascli')) {
            /// calculamos las ventas e impuestos de este mes
            $this->calcular_facturas($stats, $stats['desde'], $stats['hasta']);

            /// calculamos las ventas e impuestos del mes pasado
            $this->calcular_facturas($stats, $desde_anterior, $hasta_anterior, TRUE);
        }

        if ($this->db->table_exists('facturasprov')) {
            /// calculamos las compras e impuestos de este mes
            $this->calcular_facturas($stats, $stats['desde'], $stats['hasta'], FALSE, 'facturasprov');

            /// calculamos las compras e impuestos del mes pasado
            $this->calcular_facturas($stats, $desde_anterior, $hasta_anterior, TRUE, 'facturasprov');
        }

        if ($this->db->table_exists('co_partidas') && $this->empresa->codpais == 'ESP') {
            /// calculamos el saldo de todos aquellos asientos que afecten a caja y no se correspondan con facturas
            $sql = "select sum(debe-haber) as total from co_partidas where codsubcuenta LIKE '57%' and idasiento"
                . " in (select idasiento from co_asientos where tipodocumento IS NULL"
                . " and fecha >= " . $this->empresa->var2str($stats['desde'])
                . " and fecha <= " . $this->empresa->var2str($stats['hasta']) . ");";

            $data = $this->db->select($sql);
            if ($data) {
                $saldo = floatval($data[0]['total']);
                if ($saldo < 0) {
                    $stats['impuestos'] += abs($saldo);
                }
            }

            /// calculamos el saldo de todos aquellos asientos que afecten a caja y no se correspondan con facturas
            $sql = "select sum(debe-haber) as total from co_partidas where codsubcuenta LIKE '57%' and idasiento"
                . " in (select idasiento from co_asientos where tipodocumento IS NULL"
                . " and fecha >= " . $this->empresa->var2str($desde_anterior)
                . " and fecha <= " . $this->empresa->var2str($hasta_anterior) . ");";

            $data = $this->db->select($sql);
            if ($data) {
                $saldo = floatval($data[0]['total']);
                if ($saldo < 0) {
                    $stats['impuestos_anterior'] += abs($saldo);
                }
            }
        }

        if ($pendiente) {
            if ($this->db->table_exists('facturascli')) {
                /// calculamos las facturas de venta vencidas hasta hoy
                $sql = "select sum(totaleuros) as total, sum(neto/tasaconv) as neto from facturascli"
                    . " where pagada = false and vencimiento <= " . $this->empresa->var2str($stats['hasta']) . ";";

                $data = $this->db->select($sql);
                if ($data) {
                    if ($this->neto) {
                        $stats['ventas_sinpagar'] = $this->euro_convert(floatval($data[0]['neto']));
                    } else {
                        $stats['ventas_sinpagar'] = $this->euro_convert(floatval($data[0]['total']));
                    }
                }
            }

            if ($this->db->table_exists('albaranescli')) {
                /// calculamos los albaranes de venta pendientes
                $sql = "select sum(totaleuros) as total, sum(neto/tasaconv) as neto from albaranescli"
                    . " where idfactura IS NULL;";

                $data = $this->db->select($sql);
                if ($data) {
                    if ($this->neto) {
                        $stats['ventas_albaranes_pte'] = $this->euro_convert(floatval($data[0]['neto']));
                    } else {
                        $stats['ventas_albaranes_pte'] = $this->euro_convert(floatval($data[0]['total']));
                    }
                }
            }

            if ($this->db->table_exists('pedidoscli')) {
                /// calculamos los pedidos de venta pendientes
                $sql = "select sum(totaleuros) as total, sum(neto/tasaconv) as neto from pedidoscli"
                    . " where status = 0;";

                $data = $this->db->select($sql);
                if ($data) {
                    if ($this->neto) {
                        $stats['ventas_pedidos_pte'] = $this->euro_convert(floatval($data[0]['neto']));
                    } else {
                        $stats['ventas_pedidos_pte'] = $this->euro_convert(floatval($data[0]['total']));
                    }
                }
            }

            if ($this->db->table_exists('facturasprov')) {
                /// calculamos las facturas de compra sin pagar
                $sql = "select sum(totaleuros) as total, sum(neto/tasaconv) as neto from facturasprov"
                    . " where pagada = false;";

                $data = $this->db->select($sql);
                if ($data) {
                    if ($this->neto) {
                        $stats['compras_sinpagar'] = $this->euro_convert(floatval($data[0]['neto']));
                    } else {
                        $stats['compras_sinpagar'] = $this->euro_convert(floatval($data[0]['total']));
                    }
                }
            }

            if ($this->db->table_exists('albaranesprov')) {
                /// calculamos los albaranes de compra pendientes
                $sql = "select sum(totaleuros) as total, sum(neto/tasaconv) as neto from albaranesprov"
                    . " where idfactura IS NULL;";

                $data = $this->db->select($sql);
                if ($data) {
                    if ($this->neto) {
                        $stats['compras_albaranes_pte'] = $this->euro_convert(floatval($data[0]['neto']));
                    } else {
                        $stats['compras_albaranes_pte'] = $this->euro_convert(floatval($data[0]['total']));
                    }
                }
            }

            if ($this->db->table_exists('pedidosprov')) {
                /// calculamos los pedidos de compra pendientes
                $sql = "select sum(totaleuros) as total, sum(neto/tasaconv) as neto from pedidosprov"
                    . " where idalbaran IS NULL;";

                $data = $this->db->select($sql);
                if ($data) {
                    if ($this->neto) {
                        $stats['compras_pedidos_pte'] = $this->euro_convert(floatval($data[0]['neto']));
                    } else {
                        $stats['compras_pedidos_pte'] = $this->euro_convert(floatval($data[0]['total']));
                    }
                }
            }
        }

        $stats['impuestos_mejora'] = $this->calcular_diff($stats['impuestos'], $stats['impuestos_anterior']);

        $stats['beneficios'] = $stats['ventas'] - $stats['compras'] - $stats['impuestos'];
        $stats['beneficios_anterior'] = $stats['ventas_anterior'] - $stats['compras_anterior'] - $stats['impuestos_anterior'];
        $stats['beneficios_mejora'] = $this->calcular_diff($stats['beneficios'], $stats['beneficios_anterior']);
    }

    private function calcular_facturas(&$stats, $desde, $hasta, $anterior = FALSE, $tabla = 'facturascli')
    {
        /// utilizamos campo y extra para construir el nombre del campo, así la función es más versátil
        $campo = 'ventas';
        if ($tabla != 'facturascli') {
            $campo = 'compras';
        }

        $extra = '';
        if ($anterior) {
            $extra = '_anterior';
        }

        /// primero consultamos en la divisa de la empresa
        $sql = "select sum(total) as total, sum(neto) as neto, sum(totaliva+totalrecargo-totalirpf) as impuestos"
            . " from " . $tabla . " where coddivisa = " . $this->empresa->var2str($this->empresa->coddivisa)
            . " and fecha >= " . $this->empresa->var2str($desde)
            . " and fecha <= " . $this->empresa->var2str($hasta) . ";";

        $data = $this->db->select($sql);
        if ($data) {
            $stats[$campo . $extra] = floatval($data[0]['total']);
            $stats[$campo . $extra . '_neto'] = floatval($data[0]['neto']);

            if ($tabla == 'facturascli') {
                $stats['impuestos' . $extra] = floatval($data[0]['impuestos']);
            } else {
                $stats['impuestos' . $extra] -= floatval($data[0]['impuestos']);
            }
        }

        /// ahora consultamos en el resto de divisas y convertimos los valores
        $sql = "select sum(totaleuros) as total, sum(neto/tasaconv) as neto, sum((totaliva+totalrecargo-totalirpf)/tasaconv) as impuestos"
            . " from " . $tabla . " where coddivisa != " . $this->empresa->var2str($this->empresa->coddivisa)
            . " and fecha >= " . $this->empresa->var2str($desde)
            . " and fecha <= " . $this->empresa->var2str($hasta) . ";";

        $data = $this->db->select($sql);
        if ($data) {
            $stats[$campo . $extra] += $this->euro_convert(floatval($data[0]['total']));
            $stats[$campo . $extra . '_neto'] += $this->euro_convert(floatval($data[0]['neto']));

            if ($tabla == 'facturascli') {
                $stats['impuestos' . $extra] += $this->euro_convert(floatval($data[0]['impuestos']));
            } else {
                $stats['impuestos' . $extra] -= $this->euro_convert(floatval($data[0]['impuestos']));
            }
        }

        if ($this->neto) {
            $stats[$campo . '_mejora'] = $this->calcular_diff($stats[$campo . '_neto'], $stats[$campo . '_anterior_neto']);
        } else {
            $stats[$campo . '_mejora'] = $this->calcular_diff($stats[$campo], $stats[$campo . '_anterior']);
        }
    }

    private function calcular_diff($nuevo, $anterior)
    {
        if ($nuevo == 0 || $anterior == 0) {
            if ($nuevo == 0 && $anterior > 0) {
                return -100;
            } else if ($nuevo > 0 && $anterior == 0) {
                return 100;
            }

            return 0;
        } else if ($nuevo != $anterior) {
            if ($anterior < 0) {
                return 0 - (($nuevo * 100 / $anterior) - 100);
            }

            return ($nuevo * 100 / $anterior) - 100;
        }

        return 0;
    }

    private function leer_noticias()
    {
        $this->noticias = $this->cache->get_array('community_changelog');
        if (!$this->noticias) {
            $data = fs_file_get_contents(FS_COMMUNITY_URL . '/index.php?page=community_changelog&json=TRUE', 5);
            if ($data) {
                $this->noticias = json_decode($data);

                /// guardamos en caché
                $this->cache->set('community_changelog', $this->noticias);
            }
        }
    }
}
