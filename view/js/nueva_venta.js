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

var numlineas = 0;
var fs_nf0 = 2;
var fs_nf0_art = 2;
var all_direcciones = [];
var all_impuestos = [];
var default_impuesto = '';
var all_series = [];
var cliente = false;
var nueva_venta_url = '';
var fin_busqueda1 = true;
var fin_busqueda2 = true;
var siniva = false;
var irpf = 0;
var solo_con_stock = true;

function usar_cliente(codcliente)
{
    if (nueva_venta_url !== '')
    {
        $.getJSON(nueva_venta_url, 'datoscliente=' + codcliente, function (json) {
            cliente = json;
            document.f_buscar_articulos.codcliente.value = cliente.codcliente;
            if (cliente.regimeniva == 'Exento') {
                irpf = 0;
                for (var j = 0; j < numlineas; j++) {
                    if ($("#linea_" + j).length > 0) {
                        $("#iva_" + j).val(0);
                        $("#recargo_" + j).val(0);
                    }
                }
            }
            recalcular();
        });
    }
}

function usar_serie()
{
    for (var i = 0; i < all_series.length; i++) {
        if (all_series[i].codserie == $("#codserie").val()) {
            siniva = all_series[i].siniva;
            irpf = all_series[i].irpf;

            for (var j = 0; j < numlineas; j++) {
                if ($("#linea_" + j).length > 0) {
                    if (siniva) {
                        $("#iva_" + j).val(0);
                        $("#recargo_" + j).val(0);
                    }
                }
            }

            break;
        }
    }
}

function usar_almacen()
{
    document.f_buscar_articulos.codalmacen.value = $("#codalmacen").val();
}

function usar_divisa()
{
    document.f_buscar_articulos.coddivisa.value = $("#coddivisa").val();
}

function usar_direccion()
{
    for (var i = 0; i < all_direcciones.length; i++) {
        if (all_direcciones[i].id == $('select[name="coddir"]').val()) {
            $('select[name="codpais"]').val(all_direcciones[i].codpais);
            $('input[name="provincia"]').val(all_direcciones[i].provincia);
            $('input[name="ciudad"]').val(all_direcciones[i].ciudad);
            $('input[name="codpostal"]').val(all_direcciones[i].codpostal);
            $('input[name="direccion"]').val(all_direcciones[i].direccion);
            $('input[name="apartado"]').val(all_direcciones[i].apartado);
        }
    }
}

function usar_direccion_envio()
{
    for (var i = 0; i < all_direcciones.length; i++) {
        if ($('select[name="envio_coddir"]').val() == '') {
            $('input[name="envio_provincia"]').val('');
            $('input[name="envio_ciudad"]').val('');
            $('input[name="envio_codpostal"]').val('');
            $('input[name="envio_direccion"]').val('');
            $('input[name="envio_apartado"]').val('');
            break;
        } else if (all_direcciones[i].id == $('select[name="envio_coddir"]').val()) {
            $('select[name="envio_codpais"]').val(all_direcciones[i].codpais);
            $('input[name="envio_provincia"]').val(all_direcciones[i].provincia);
            $('input[name="envio_ciudad"]').val(all_direcciones[i].ciudad);
            $('input[name="envio_codpostal"]').val(all_direcciones[i].codpostal);
            $('input[name="envio_direccion"]').val(all_direcciones[i].direccion);
            $('input[name="envio_apartado"]').val(all_direcciones[i].apartado);
        }
    }
}

function recalcular()
{
    var l_uds = 0;
    var l_pvp = 0;
    var l_dto = 0;
    var l_dto2 = 0;
    var l_dto3 = 0;
    var l_dto4 = 0;
    var show = [];
    var l_neto = 0;
    var l_iva = 0;
    var l_irpf = 0;
    var l_recargo = 0;
    var netosindto = 0;
    var netocondto = 0;
    var neto = 0;
    var total_iva = 0;
    var total_irpf = 0;
    var total_recargo = 0;
    var adto1 = 0;
    var adto2 = 0;
    var adto3 = 0;
    var adto4 = 0;
    var adto5 = 0;
    var dto_totales = 0;
    var dto_linea = 0;

    adto1 = parseFloat($("#adtopor1").val());
    if (isNaN(adto1)) {
        adto1 = 0;
    }
    adto2 = parseFloat($("#adtopor2").val());
    if (isNaN(adto2)) {
        adto2 = 0;
    }
    adto3 = parseFloat($("#adtopor3").val());
    if (isNaN(adto3)) {
        adto3 = 0;
    }
    adto4 = parseFloat($("#adtopor4").val());
    if (isNaN(adto4)) {
        adto4 = 0;
    }
    adto5 = parseFloat($("#adtopor5").val());
    if (isNaN(adto5)) {
        adto5 = 0;
    }

    dto_totales = calc_due([adto1, adto2, adto3, adto4, adto5]);
    for (var i = 0; i < numlineas; i++) {
        if ($("#linea_" + i).length > 0) {
            /// cambiamos coma por punto
            if (input_number == 'text' && $("#cantidad_" + i).val().search(",") >= 0) {
                $("#cantidad_" + i).val($("#cantidad_" + i).val().replace(",", "."));
            }
            if ($("#pvp_" + i).val().search(",") >= 0) {
                $("#pvp_" + i).val($("#pvp_" + i).val().replace(",", "."));
            }
            if ($("#dto_" + i).val().search(",") >= 0) {
                $("#dto_" + i).val($("#dto_" + i).val().replace(",", "."));
            }
            if ($("#dto2_" + i).val() >= 0) {
                $("#dto2_" + i).val($("#dto2_" + i).val().replace(",", "."));
            }
            if ($("#dto3_" + i).val() >= 0) {
                $("#dto3_" + i).val($("#dto3_" + i).val().replace(",", "."));
            }
            if ($("#dto4_" + i).val() >= 0) {
                $("#dto4_" + i).val($("#dto4_" + i).val().replace(",", "."));
            }
            if ($("#iva_" + i).val().search(",") >= 0) {
                $("#iva_" + i).val($("#iva_" + i).val().replace(",", "."));
            }
            if ($("#irpf_" + i).val().search(",") >= 0) {
                $("#irpf_" + i).val($("#irpf_" + i).val().replace(",", "."));
            }
            if ($("#recargo_" + i).val().search(",") >= 0) {
                $("#recargo_" + i).val($("#recargo_" + i).val().replace(",", "."));
            }
            
            l_uds = parseFloat($("#cantidad_" + i).val());
            if (isNaN(l_uds)) {
                l_uds = 0;
            }
            l_pvp = parseFloat($("#pvp_" + i).val());
            if (isNaN(l_pvp)) {
                l_pvp = 0;
            }
            l_dto = parseFloat($("#dto_" + i).val());
            if (isNaN(l_dto)) {
                l_dto = 0;
            }
            l_dto2 = parseFloat($("#dto2_" + i).val());
            if (isNaN(l_dto2)) {
                l_dto2 = 0;
            }
            l_dto3 = parseFloat($("#dto3_" + i).val());
            if (isNaN(l_dto3)) {
                l_dto3 = 0;
            }
            l_dto4 = parseFloat($("#dto4_" + i).val());
            if (isNaN(l_dto4)) {
                l_dto4 = 0;
            }
            
            if ((l_dto2 == 0 && l_dto3 == 0 && l_dto4 == 0)) {
                show[i] = false;
            } else {
                show[i] = true;
            }
            
            dto_linea = calc_due([l_dto, l_dto2, l_dto3, l_dto4]);
            
            // Total neto antes de descuentos globales
            netosindto += l_uds * l_pvp * dto_linea;
            l_neto = l_uds * l_pvp * dto_linea;

            l_iva = parseFloat($("#iva_" + i).val());
            l_irpf = parseFloat($("#irpf_" + i).val());
            if (isNaN(l_irpf)) {
                l_irpf = 0;
            }
            l_recargo = parseFloat($("#recargo_" + i).val());
            if (isNaN(l_recargo)) {
                l_recargo = 0;
            }
            
            $("#neto_" + i).val(l_neto);
            if (numlineas == 1) {
                $("#total_" + i).val(l_neto + l_neto * (l_iva - l_irpf + l_recargo) / 100);
            } else {
                $("#total_" + i).val(number_format(l_neto + (l_neto * (l_iva - l_irpf + l_recargo) / 100), fs_nf0, '.', ''));
            }
            l_neto = l_uds * l_pvp * dto_linea;

            // ESTOS YA SON VALORES FINALES, SE REDONDEAN AHORA
            // Total neto despues de descuentos globales
            netocondto += l_neto;
            total_iva += l_neto * l_iva / 100;
            total_irpf += l_neto * l_irpf / 100;
            total_recargo += l_neto * l_recargo / 100;

            /// adaptamos el alto del textarea al texto
            var txt = $("textarea[name='desc_" + i + "']").val();
            txt = txt.split(/\r*\n/);
            if (txt.length > 1) {
                $("textarea[name='desc_" + i + "']").prop('rows', txt.length);
            }
        }
    }

    netocondto = netosindto * dto_totales;
    neto = netocondto;
    total_iva = total_iva * dto_totales;
    total_recargo = total_recargo * dto_totales;
    total_irpf = total_irpf * dto_totales;
    
    $("#anetosindto").html(fs_round(netosindto, fs_nf0));
    $("#aneto").html(fs_round(netocondto, fs_nf0));
    $("#aiva").html(fs_round(total_iva, fs_nf0));
    $("#are").html(fs_round(total_recargo, fs_nf0));
    $("#airpf").html(fs_round(total_irpf, fs_nf0));
    $("#atotal").val(fs_round(neto + total_iva - total_irpf + total_recargo, fs_nf0));

    // ELIMINAR DESPUÉS DE REVISAR
    console.log("Valores antes de redondeo: ");
    console.log("Subtotal: " + netosindto);
    console.log("Descuento sobre el total: " + netosindto);
    console.log("Base imponible: " + netocondto);
    console.log("Impuestos: " + total_iva);
    console.log("RE: " + total_recargo);
    console.log("IRPF: " + total_irpf);
    console.log("Total: " + (neto + total_iva - total_irpf + total_recargo));
    console.log("");
    console.log("Valores despues de redondeo: ");
    console.log("Subtotal: " + fs_round(netosindto, fs_nf0));
    console.log("Descuento sobre el total: " + fs_round(netosindto, fs_nf0));
    console.log("Base imponible: " + fs_round(netocondto, fs_nf0));
    console.log("Impuestos: " + fs_round(total_iva, fs_nf0));
    console.log("RE: " + fs_round(total_recargo, fs_nf0));
    console.log("IRPF: " + fs_round(total_irpf, fs_nf0));
    console.log("Total: " + fs_round(neto + total_iva - total_irpf + total_recargo, fs_nf0));
    
    // Descuentos de líneas del documento
    Array.prototype.contains = function(elem) {
        for (var i in this) {
            if (this[i] == elem) {
                return true;
            }
        }
        return false;
    }
    
    if(show.contains(true)) {
        $(".dtosl").show();
    } else {
        // Si está indefinido asignamos false, ya que es la primera vez
        if(typeof cliente.dtosl === 'undefined'){
            cliente.dtosl = false;
        }
        if (cliente.dtosl) {
            $(".dtosl").show();
        } else {
            $(".dtosl").hide();
        }
    }
    
    // Descuentos totales del documento
    // Si está indefinido asignamos false, ya que es la primera vez
    if (typeof cliente.dtost === 'undefined') {
        cliente.dtost = false;
    }
    if (netosindto != netocondto || cliente.dtost) {
        $(".dtost").show();
    } else {
        $(".dtost").hide();
    }

    if (total_recargo == 0 && !cliente.recargo) {
        $(".recargo").hide();
    } else {
        $(".recargo").show();
    }

    if (total_irpf == 0 && irpf == 0) {
        $(".irpf").hide();
    } else {
        $(".irpf").show();
    }
}

function ajustar_neto(i)
{
    var l_uds = 0;
    var l_pvp = 0;
    var l_dto = 0;
    var l_dto2 = 0;
    var l_dto3 = 0;
    var l_dto4 = 0;
    var l_neto = 0;

    if ($("#linea_" + i).length > 0) {
        /// cambiamos coma por punto
        if ($("#neto_" + i).val().search(",") >= 0) {
            $("#neto_" + i).val($("#neto_" + i).val().replace(",", "."));
        }

        l_uds = parseFloat($("#cantidad_" + i).val());
        if (isNaN(l_uds)) {
            l_uds = 0;
        }
        l_pvp = parseFloat($("#pvp_" + i).val());
        if (isNaN(l_pvp)) {
            l_pvp = 0;
        }
        l_dto = parseFloat($("#dto_" + i).val());
        l_dto2 = parseFloat($("#dto2_" + i).val());
        l_dto3 = parseFloat($("#dto3_" + i).val());
        l_dto4 = parseFloat($("#dto4_" + i).val());
        l_neto = parseFloat($("#neto_" + i).val());
        if (isNaN(l_neto)) {
            l_neto = 0;
        } else if (l_neto < 0) {
            l_neto = Math.abs(l_neto);
        }

        if (l_neto <= l_pvp * l_uds) {
            l_dto = 100 - 100 * l_neto / (l_pvp * l_uds);
            if (isNaN(l_dto)) {
                l_dto = 0;
            }

            l_dto2 = l_dto2;
            if (isNaN(l_dto2)) {
                l_dto2 = 0;
            }

            l_dto3 = l_dto3;
            if (isNaN(l_dto3)) {
                l_dto3 = 0;
            }

            l_dto4 = l_dto4;
            if (isNaN(l_dto4)) {
                l_dto4 = 0;
            }
        } else {
            l_dto = 0;
            l_pvp = 100 * l_neto / (l_uds * (100 - l_dto));
            if (isNaN(l_pvp)) {
                l_pvp = 0;
            }

            l_pvp = fs_round(l_pvp, fs_nf0_art);
        }

        $("#pvp_" + i).val(l_pvp);
        $("#dto_" + i).val(l_dto);
        $("#dto2_" + i).val(l_dto2);
        $("#dto3_" + i).val(l_dto3);
        $("#dto4_" + i).val(l_dto4);
    }

    recalcular();
}

function ajustar_total(i)
{
    var l_uds = 0;
    var l_pvp = 0;
    var l_dto = 0;
    var l_iva = 0;
    var l_irpf = 0;
    var l_recargo = 0;
    var l_neto = 0;
    var l_total = 0;

    if ($("#linea_" + i).length > 0) {
        /// cambiamos coma por punto
        if ($("#total_" + i).val().search(",") >= 0) {
            $("#total_" + i).val($("#total_" + i).val().replace(",", "."));
        }

        l_uds = parseFloat($("#cantidad_" + i).val());
        if (isNaN(l_uds)) {
            l_uds = 0;
        } else if (l_uds < 0) {
            l_uds = Math.abs(l_uds);
        }
        l_pvp = parseFloat($("#pvp_" + i).val());
        if (isNaN(l_pvp)) {
            l_pvp = 0;
        } else if (l_pvp < 0) {
            l_pvp = Math.abs(l_pvp);
        }
        l_dto = parseFloat($("#dto_" + i).val());
        if (isNaN(l_dto)) {
            l_dto = 0;
        } else if (l_dto < 0) {
            l_dto = Math.abs(l_dto);
        }
        l_iva = parseFloat($("#iva_" + i).val());
        if (isNaN(l_iva)) {
            l_iva = 0;
        } else if (l_iva < 0) {
            l_iva = Math.abs(l_iva);
        }
        l_recargo = parseFloat($("#recargo_" + i).val());
        if (isNaN(l_recargo)) {
            l_recargo = 0;
        } else if (l_recargo < 0) {
            l_recargo = Math.abs(l_recargo);
        }
        l_irpf = parseFloat($("#irpf_" + i).val());
        if (isNaN(l_irpf)) {
            l_irpf = 0;
        } else if (l_irpf < 0) {
            l_irpf = Math.abs(l_irpf);
        }

        l_total = parseFloat($("#total_" + i).val());
        if (isNaN(l_total)) {
            l_total = 0;
        } else if (l_total < 0) {
            l_total = Math.abs(l_total);
        }

        if (l_total <= l_pvp * l_uds + (l_pvp * l_uds * (l_iva - l_irpf + l_recargo) / 100)) {
            l_neto = 100 * l_total / (100 + l_iva - l_irpf + l_recargo);
            l_dto = 100 - 100 * l_neto / (l_pvp * l_uds);
            if (isNaN(l_dto)) {
                l_dto = 0;
            }

            l_dto = fs_round(l_dto, 2);
        } else {
            l_dto = 0;
            l_neto = 100 * l_total / (100 + l_iva - l_irpf + l_recargo);
            l_pvp = fs_round(l_neto / l_uds, fs_nf0_art);
        }

        $("#pvp_" + i).val(l_pvp);
        $("#dto_" + i).val(l_dto);
    }

    recalcular();
}

function ajustar_iva(num)
{
    if ($("#linea_" + num).length > 0) {
        if (cliente.regimeniva == 'Exento') {
            $("#iva_" + num).val(0);
            $("#recargo_" + num).val(0);

            bootbox.alert({
                message: 'El cliente tiene regimen de IVA: ' + cliente.regimeniva,
                title: "<b>Atención</b>"
            });
        } else if (siniva && $("#iva_" + num).val() != 0) {
            $("#iva_" + num).val(0);
            $("#recargo_" + num).val(0);

            bootbox.alert({
                message: 'La serie selecciona es sin IVA.',
                title: "<b>Atención</b>"
            });
        } else if (cliente.recargo) {
            for (var i = 0; i < all_impuestos.length; i++) {
                if ($("#iva_" + num).val() == all_impuestos[i].iva) {
                    $("#recargo_" + num).val(all_impuestos[i].recargo);
                }
            }
        }
    }

    recalcular();
}

function aux_all_dtos(dto2, dto3, dto4)
{
    var html = '<td class="dtosl"><input type="text" id="dto2_' + numlineas + '" name="dto2_' + numlineas + 
        '" value="' + dto2 + '" class="form-control text-right" onkeyup="recalcular()" onclick="this.select()" autocomplete="off"/></td>';
    html += '<td class="dtosl"><input type="text" id="dto3_' + numlineas + '" name="dto3_' + numlineas + 
        '" value="' + dto3 + '" class="form-control text-right" onkeyup="recalcular()" onclick="this.select()" autocomplete="off"/></td>';
    html += '<td class="dtosl"><input type="text" id="dto4_' + numlineas + '" name="dto4_' + numlineas + 
        '" value="' + dto4 + '" class="form-control text-right" onkeyup="recalcular()" onclick="this.select()" autocomplete="off"/></td>';
    return html;
}

function aux_all_impuestos(num, codimpuesto)
{
    var iva = 0;
    var recargo = 0;
    if (cliente.regimeniva != 'Exento' && !siniva) {
        for (var i = 0; i < all_impuestos.length; i++) {
            if (all_impuestos[i].codimpuesto == codimpuesto || codimpuesto == '') {
                iva = all_impuestos[i].iva;
                if (cliente.recargo) {
                    recargo = all_impuestos[i].recargo;
                }
                break;
            }
        }
    }

    var html = "<td><select id=\"iva_" + num + "\" class=\"form-control\" name=\"iva_" + num + "\" onchange=\"ajustar_iva('" + num + "')\">";
    for (var i = 0; i < all_impuestos.length; i++) {
        if (iva == all_impuestos[i].iva) {
            html += "<option value=\"" + all_impuestos[i].iva + "\" selected=\"\">" + all_impuestos[i].descripcion + "</option>";
        } else {
            html += "<option value=\"" + all_impuestos[i].iva + "\">" + all_impuestos[i].descripcion + "</option>";
        }
    }
    html += "</select></td>";

    html += "<td class=\"recargo\"><input type=\"text\" class=\"form-control text-right\" id=\"recargo_" + num + "\" name=\"recargo_" + num +
            "\" value=\"" + recargo + "\" onclick=\"this.select()\" onkeyup=\"recalcular()\" autocomplete=\"off\"/></td>";

    html += "<td class=\"irpf\"><input type=\"text\" class=\"form-control text-right\" id=\"irpf_" + num + "\" name=\"irpf_" + num +
            "\" value=\"" + irpf + "\" onclick=\"this.select()\" onkeyup=\"recalcular()\" autocomplete=\"off\"/></td>";

    return html;
}

function add_articulo(ref, desc, pvp, dto, codimpuesto, cantidad, codcombinacion, dto2 = 0, dto3 = 0, dto4 = 0)
{
    if (typeof codcombinacion == 'undefined') {
        codcombinacion = '';
    }

    desc = Base64.decode(desc);
    $("#lineas_albaran").append("<tr id=\"linea_" + numlineas + "\">\n\
      <td><input type=\"hidden\" name=\"idlinea_" + numlineas + "\" value=\"-1\"/>\n\
         <input type=\"hidden\" name=\"referencia_" + numlineas + "\" value=\"" + ref + "\"/>\n\
         <input type=\"hidden\" name=\"codcombinacion_" + numlineas + "\" value=\"" + codcombinacion + "\"/>\n\
         <div class=\"form-control\"><small><a target=\"_blank\" href=\"index.php?page=ventas_articulo&ref=" + ref + "\">" + ref + "</a></small></div></td>\n\
      <td><textarea class=\"form-control\" id=\"desc_" + numlineas + "\" name=\"desc_" + numlineas + "\" rows=\"1\">" + desc + "</textarea></td>\n\
      <td><input type=\"" + input_number + "\" step=\"any\" id=\"cantidad_" + numlineas + "\" class=\"form-control text-right\" name=\"cantidad_" + numlineas +
            "\" onchange=\"recalcular()\" onkeyup=\"recalcular()\" autocomplete=\"off\" value=\"" + cantidad + "\"/></td>\n\
      <td><button class=\"btn btn-sm btn-danger\" type=\"button\" onclick=\"$('#linea_" + numlineas + "').remove();recalcular();\">\n\
         <span class=\"glyphicon glyphicon-trash\"></span></button></td>\n\
      <td><input type=\"text\" class=\"form-control text-right\" id=\"pvp_" + numlineas + "\" name=\"pvp_" + numlineas + "\" value=\"" + pvp +
            "\" onkeyup=\"recalcular()\" onclick=\"this.select()\" autocomplete=\"off\"/></td>\n\
      <td><input type=\"text\" id=\"dto_" + numlineas + "\" name=\"dto_" + numlineas + "\" value=\"" + dto +
            "\" class=\"form-control text-right\" onkeyup=\"recalcular()\" onchange=\"recalcular()\" onclick=\"this.select()\" autocomplete=\"off\"/></td>\n\
    " + aux_all_dtos(dto2, dto3, dto4) + "\n\
      <td><input type=\"text\" class=\"form-control text-right\" id=\"neto_" + numlineas + "\" name=\"neto_" + numlineas +
            "\" onchange=\"ajustar_neto(" + numlineas + ")\" onclick=\"this.select()\" autocomplete=\"off\"/></td>\n\
      " + aux_all_impuestos(numlineas, codimpuesto) + "\n\
      <td class=\"warning\" title=\"Cálculo aproximado del total de la linea\">\n\
         <input type=\"text\" class=\"form-control text-right\" id=\"total_" + numlineas + "\" name=\"total_" + numlineas +
            "\" onchange=\"ajustar_total(" + numlineas + ")\" onclick=\"this.select()\" autocomplete=\"off\"/></td></tr>");
    numlineas += 1;
    $("#numlineas").val(numlineas);
    recalcular();

    $("#modal_articulos").modal('hide');

    $("#desc_" + (numlineas - 1)).select();
    return false;
}

function add_articulo_atributos(ref, desc, pvp, dto, codimpuesto, cantidad)
{
    if (nueva_venta_url !== '') {
        $.ajax({
            type: 'POST',
            url: nueva_venta_url,
            dataType: 'html',
            data: "referencia4combi=" + ref + "&desc=" + desc + "&pvp=" + pvp + "&dto=" + dto
                    + "&codimpuesto=" + codimpuesto + "&cantidad=" + cantidad,
            success: function (datos) {
                $("#nav_articulos").hide();
                $("#search_results").html(datos);
            },
            error: function () {
                bootbox.alert({
                    message: 'Se ha producido un error al obtener los atributos.',
                    title: "<b>Atención</b>"
                });
            }
        });
    }
}

function add_linea_libre()
{
    $("#lineas_albaran").append("<tr id=\"linea_" + numlineas + "\">\n\
      <td><input type=\"hidden\" name=\"idlinea_" + numlineas + "\" value=\"-1\"/>\n\
         <input type=\"hidden\" name=\"referencia_" + numlineas + "\"/>\n\
         <input type=\"hidden\" name=\"codcombinacion_" + numlineas + "\"/>\n\
         <div class=\"form-control\"></div></td>\n\
      <td><textarea class=\"form-control\" id=\"desc_" + numlineas + "\" name=\"desc_" + numlineas + "\" rows=\"1\"></textarea></td>\n\
      <td><input type=\"" + input_number + "\" step=\"any\" id=\"cantidad_" + numlineas + "\" class=\"form-control text-right\" name=\"cantidad_" + numlineas +
            "\" onchange=\"recalcular()\" onkeyup=\"recalcular()\" autocomplete=\"off\" value=\"1\"/></td>\n\
      <td><button class=\"btn btn-sm btn-danger\" type=\"button\" onclick=\"$('#linea_" + numlineas + "').remove();recalcular();\">\n\
         <span class=\"glyphicon glyphicon-trash\"></span></button></td>\n\
      <td><input type=\"text\" class=\"form-control text-right\" id=\"pvp_" + numlineas + "\" name=\"pvp_" + numlineas + "\" value=\"0\"\n\
          onkeyup=\"recalcular()\" onclick=\"this.select()\" autocomplete=\"off\"/></td>\n\
      <td><input type=\"text\" id=\"dto_" + numlineas + "\" name=\"dto_" + numlineas + "\" value=\"0\" class=\"form-control text-right\"\n\
          onkeyup=\"recalcular()\" onclick=\"this.select()\" autocomplete=\"off\"/></td>\n\
      " + aux_all_dtos() + "\n\
      <td><input type=\"text\" class=\"form-control text-right\" id=\"neto_" + numlineas + "\" name=\"neto_" + numlineas +
            "\" onchange=\"ajustar_neto(" + numlineas + ")\" onclick=\"this.select()\" autocomplete=\"off\"/></td>\n\
      " + aux_all_impuestos(numlineas, default_impuesto) + "\n\
      <td class=\"warning\" title=\"Cálculo aproximado del total de la linea\">\n\
         <input type=\"text\" class=\"form-control text-right\" id=\"total_" + numlineas + "\" name=\"total_" + numlineas +
            "\" onchange=\"ajustar_total(" + numlineas + ")\" onclick=\"this.select()\" autocomplete=\"off\"/></td></tr>");
    numlineas += 1;
    $("#numlineas").val(numlineas);
    recalcular();

    $("#desc_" + (numlineas - 1)).select();
    return false;
}

function get_precios(ref)
{
    if (nueva_venta_url !== '') {
        $.ajax({
            type: 'POST',
            url: nueva_venta_url,
            dataType: 'html',
            data: "referencia4precios=" + ref + "&codcliente=" + cliente.codcliente,
            success: function (datos) {
                $("#nav_articulos").hide();
                $("#search_results").html(datos);
            },
            error: function () {
                bootbox.alert({
                    message: 'Se ha producido un error al obtener los precios.',
                    title: "<b>Atención</b>"
                });
            }
        });
    }
}

function new_articulo()
{
    if (nueva_venta_url !== '') {
        $.ajax({
            type: 'POST',
            url: nueva_venta_url + '&new_articulo=TRUE',
            dataType: 'json',
            data: $("form[name=f_nuevo_articulo]").serialize(),
            success: function (datos) {
                if (typeof datos[0] == 'undefined') {
                    bootbox.alert({
                        message: 'Se ha producido un error al crear el artículo.',
                        title: "<b>Atención</b>"
                    });
                } else {
                    document.f_buscar_articulos.query.value = document.f_nuevo_articulo.referencia.value;
                    $("#nav_articulos li").each(function () {
                        $(this).removeClass("active");
                    });
                    $("#li_mis_articulos").addClass('active');
                    $("#search_results").show();
                    $("#nuevo_articulo").hide();

                    add_articulo(datos[0].referencia, Base64.encode(datos[0].descripcion), datos[0].pvp, 0, datos[0].codimpuesto, 1);
                }
            },
            error: function () {
                bootbox.alert({
                    message: 'Se ha producido un error al crear el artículo.',
                    title: "<b>Atención</b>"
                });
            }
        });
    }
}

function buscar_articulos()
{
    document.f_nuevo_articulo.referencia.value = document.f_buscar_articulos.query.value;

    if (document.f_buscar_articulos.query.value === '') {
        $("#nav_articulos").hide();
        $("#search_results").html('');
        $("#nuevo_articulo").hide();

        fin_busqueda1 = true;
        fin_busqueda2 = true;
    } else {
        $("#nav_articulos").show();

        if (nueva_venta_url !== '') {
            fin_busqueda1 = false;
            $.getJSON(nueva_venta_url, $("form[name=f_buscar_articulos]").serialize(), function (json) {
                var items = [];
                var insertar = false;
                $.each(json, function (key, val) {
                    var stock = val.stockalm;
                    if (val.nostock) {
                        stock = '-';
                    } else if (val.stockalm != val.stockfis) {
                        stock += ' <span title="stock general">(' + val.stockfis + ')</span>';
                    }

                    var descripcion = Base64.encode(val.descripcion);
                    var descripcion_visible = val.descripcion;
                    if (val.codfamilia) {
                        descripcion_visible += ' <span class="label label-default" title="Familia: ' + val.codfamilia + '">'
                                + val.codfamilia + '</span>';
                    }
                    if (val.codfabricante) {
                        descripcion_visible += ' <span class="label label-default" title="Fabricante: ' + val.codfabricante + '">'
                                + val.codfabricante + '</span>';
                    }
                    if (val.trazabilidad) {
                        descripcion_visible += ' &nbsp; <i class="fa fa-code-fork" aria-hidden="true" title="Trazabilidad activada"></i>';
                    }

                    var tr_aux = '<tr>';
                    if (val.bloqueado || (val.stockalm < 1 && !val.controlstock)) {
                        tr_aux = "<tr class=\"danger\">";
                    } else if (val.stockfis < val.stockmin) {
                        tr_aux = "<tr class=\"warning\">";
                    } else if (val.stockalm > 0) {
                        tr_aux = "<tr class=\"success\">";
                    }

                    if (val.sevende) {
                        var funcion = "add_articulo('" + val.referencia + "','" + descripcion + "','" + val.pvp + "','"
                                + val.dtopor + "','" + val.codimpuesto + "','" + val.cantidad + "')";

                        if (val.tipo) {
                            funcion = "add_articulo_" + val.tipo + "('" + val.referencia + "','" + descripcion + "','"
                                    + val.pvp + "','" + val.dtopor + "','" + val.codimpuesto + "','" + val.cantidad + "')";
                        }

                        items.push(tr_aux + "<td><a href=\"#\" onclick=\"get_precios('" + val.referencia + "')\" title=\"más detalles\">\n\
                     <span class=\"glyphicon glyphicon-eye-open\"></span></a>\n\
                     &nbsp; <a href=\"#\" onclick=\"return " + funcion + "\">" + val.referencia + '</a> ' + descripcion_visible + "</td>\n\
                     <td class=\"text-right\"><a href=\"#\" onclick=\"return " + funcion + "\" title=\"actualizado el " + val.factualizado
                                + "\">" + show_precio(val.pvp * (100 - val.dtopor) / 100, val.coddivisa) + "</a></td>\n\
                     <td class=\"text-right\"><a href=\"#\" onclick=\"return " + funcion + "\" title=\"actualizado el " + val.factualizado
                                + "\">" + show_pvp_iva(val.pvp * (100 - val.dtopor) / 100, val.codimpuesto, val.coddivisa) + "</a></td>\n\
                     <td class=\"text-right\">" + stock + "</td></tr>");
                    }

                    if (val.query == document.f_buscar_articulos.query.value) {
                        insertar = true;
                        fin_busqueda1 = true;
                    }
                });

                if (items.length == 0 && !fin_busqueda1) {
                    items.push("<tr><td colspan=\"4\" class=\"warning\">Sin resultados. Usa la pestaña\n\
                              <b>Nuevo</b> para crear uno.</td></tr>");
                    document.f_nuevo_articulo.referencia.value = document.f_buscar_articulos.query.value;
                    insertar = true;
                }

                if (insertar) {
                    $("#search_results").html("<div class=\"table-responsive\"><table class=\"table table-hover\"><thead><tr>\n\
                  <th class=\"text-left\">Referencia + descripción</th>\n\
                  <th class=\"text-right\" width=\"80\">Precio</th>\n\
                  <th class=\"text-right\" width=\"80\">Precio+IVA</th>\n\
                  <th class=\"text-right\" width=\"80\">Stock</th>\n\
                  </tr></thead>" + items.join('') + "</table></div>");
                }
            });
        }
    }
}

function show_pvp_iva(pvp, codimpuesto, coddivisa)
{
    var iva = 0;
    if (cliente.regimeniva != 'Exento' && !siniva) {
        for (var i = 0; i < all_impuestos.length; i++) {
            if (all_impuestos[i].codimpuesto == codimpuesto) {
                iva = all_impuestos[i].iva;
                break;
            }
        }
    }

    return show_precio(pvp + pvp * iva / 100, coddivisa);
}

/**
 * Devuelve el escalar del descuento unificado equivalente
 * Por ejemplo: recibe descuentos = [50, 10] y devuelve 0.45
 * 
 * @param Array descuentos
 * @return float
 */
function calc_due(descuentos)
{
    return (1 - calc_desc_due(descuentos) / 100);
}

/**
 * Devuelve el descuento unificado equivalente
 * Por ejemplo: recibe descuentos = [50, 10] y devuelve 55
 * 
 * @param Array descuentos
 * @return float
 */
function calc_desc_due(descuentos)
{
    var dto = 1;
    for (var i = 0; i < descuentos.length; ++i){
        if (descuentos[i] == 0) {
            dto *= 1
        } else {
            dto *= (1 - descuentos[i] / 100);
        }
        
    }
    return (1 - dto) * 100;
}

$(document).ready(function () {
    show = false;
    $("#i_new_line").click(function () {
        $("#i_new_line").val("");
        $("#nav_articulos li").each(function () {
            $(this).removeClass("active");
        });
        $("#li_mis_articulos").addClass('active');
        $("#search_results").show();
        $("#nuevo_articulo").hide();
        $("#modal_articulos").modal('show');
        document.f_buscar_articulos.query.select();
    });

    $("#i_new_line").keyup(function () {
        document.f_buscar_articulos.query.value = $("#i_new_line").val();
        $("#i_new_line").val('');
        $("#nav_articulos li").each(function () {
            $(this).removeClass("active");
        });
        $("#li_mis_articulos").addClass('active');
        $("#search_results").html('');
        $("#search_results").show();
        $("#nuevo_articulo").hide();
        $("#modal_articulos").modal('show');
        document.f_buscar_articulos.query.select();
        buscar_articulos();
    });

    $("#f_buscar_articulos").keyup(function () {
        buscar_articulos();
    });

    $("#f_buscar_articulos").submit(function (event) {
        event.preventDefault();
        buscar_articulos();
    });

    $("#b_mis_articulos").click(function (event) {
        event.preventDefault();
        $("#nav_articulos li").each(function () {
            $(this).removeClass("active");
        });
        $("#li_mis_articulos").addClass('active');
        $("#nuevo_articulo").hide();
        $("#search_results").show();
        document.f_buscar_articulos.query.focus();
    });

    $("#b_nuevo_articulo").click(function (event) {
        event.preventDefault();
        $("#nav_articulos li").each(function () {
            $(this).removeClass("active");
        });
        $("#li_nuevo_articulo").addClass('active');
        $("#search_results").hide();
        $("#nuevo_articulo").show();
        document.f_nuevo_articulo.referencia.select();
    });
});
