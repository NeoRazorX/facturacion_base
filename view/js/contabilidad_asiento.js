/*
 * This file is part of facturacion_base
 * Copyright (C) 2014-2017  Carlos Garcia Gomez  neorazorx@gmail.com
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

var fs_nf0 = 2;
var fsc_url = '';
var numlineas = 0;

function show_buscar_subcuentas(num, tipo)
{
    $("#subcuentas").html('');
    document.f_buscar_subcuentas.fecha.value = document.f_asiento.fecha.value;
    document.f_buscar_subcuentas.tipo.value = tipo;
    document.f_buscar_subcuentas.numlinea.value = num;
    document.f_buscar_subcuentas.query.value = '';
    $("#modal_subcuentas").modal('show');
    document.f_buscar_subcuentas.query.focus();
}

function buscar_subcuentas()
{
    if (document.f_buscar_subcuentas.query.value == '') {
        $("#subcuentas").html('');
    } else {
        var datos = 'query=' + document.f_buscar_subcuentas.query.value;
        datos += "&fecha=" + document.f_buscar_subcuentas.fecha.value;
        $.ajax({
            type: 'POST',
            url: fsc_url,
            dataType: 'html',
            data: datos,
            success: function (datos) {
                var re = /<!--(.*?)-->/g;
                var m = re.exec(datos);
                if (m[1] == document.f_buscar_subcuentas.query.value)
                {
                    $("#subcuentas").html(datos);
                }
            }
        });
    }
}

function select_subcuenta(codsubcuenta, saldo, descripcion)
{
    var num = document.f_buscar_subcuentas.numlinea.value;
    if (document.f_buscar_subcuentas.tipo.value == 'subcuenta') {
        $("#codsubcuenta_" + num).val(codsubcuenta);
        $("#desc_" + num).val(Base64.decode(descripcion));
        $("#saldo_" + num).val(saldo);
    } else {
        $("#codcontrapartida_" + num).val(codsubcuenta);
        $("#saldoc_" + num).val(saldo);
        $("#iva_" + num).prop('disabled', false);
        $("#baseimp_" + num).prop('disabled', false);
        $("#cifnif_" + num).prop('disabled', false);
    }
    $("#modal_subcuentas").modal('hide');
    recalcular();
}

function clean_partida(num)
{
    $("#partida_" + num).remove();
    recalcular();
}

function recalcular()
{
    var debe = 0;
    var haber = 0;
    var iva = 0;
    var t_debe = 0;
    var t_haber = 0;
    var show_contrapartidas = false;

    for (var i = 1; i <= numlineas; i++) {
        if ($("#partida_" + i).length > 0) {
            if ($("#debe_" + i).val().search(",") >= 0)
            {
                $("#debe_" + i).val($("#debe_" + i).val().replace(",", "."));
            }
            if ($("#haber_" + i).val().search(",") >= 0)
            {
                $("#haber_" + i).val($("#haber_" + i).val().replace(",", "."));
            }
            
            debe = parseFloat($("#debe_" + i).val());
            haber = parseFloat($("#haber_" + i).val());

            if ($("#codcontrapartida_" + i).val() != '') {
                show_contrapartidas = true;

                iva = parseFloat($("#iva_" + i).val());
                if (iva == 0) {
                    $("#baseimp_" + i).val('0');
                } else {
                    if (haber == 0) {
                        $("#baseimp_" + i).val(debe * 100 / iva);
                    } else if (debe == 0) {
                        $("#baseimp_" + i).val(haber * 100 / iva);
                    } else {
                        $("#baseimp_" + i).val(0);
                    }
                }
            }

            t_debe += debe;
            t_haber += haber;
        }
    }

    document.f_asiento.importe.value = Math.max(t_debe, t_haber);
    document.f_asiento.descuadre.value = fs_round(t_debe - t_haber, fs_nf0);

    if (show_contrapartidas) {
        $(".contrapartida").show();
    } else {
        $(".contrapartida").hide();
    }
}

function asigna_concepto()
{
    document.f_asiento.concepto.value = $("#s_idconceptopar option:selected").text();
}

function guardar_asiento()
{
    $("#b_guardar_asiento").prop('disabled', true);
    $("#b_guardar_asiento_2").prop('disabled', true);
    $("#divisa").prop('disabled', false);

    var continuar = true;
    for (var i = 1; i <= numlineas; i++) {
        if ($("#partida_" + i).length > 0) {
            if ($("#codsubcuenta_" + i).val() == '') {
                bootbox.alert({
                    message: 'No has seleccionado ninguna subcuenta en la línea ' + i,
                    title: "<b>Atención</b>"
                });
                continuar = false;
                break;
            }
        }
    }

    if (!continuar) {
        $("#b_guardar_asiento").prop('disabled', false);
        $("#b_guardar_asiento_2").prop('disabled', false);
    } else if (document.f_asiento.descuadre.value == 0) {
        document.f_asiento.numlineas.value = numlineas;
        document.f_asiento.importe.disabled = false;
        document.f_asiento.submit();
    } else {
        bootbox.alert({
            message: '¡Asiento descuadrado!',
            title: "<b>Atención</b>"
        });
        $("#b_guardar_asiento").prop('disabled', false);
        $("#b_guardar_asiento_2").prop('disabled', false);
    }
}