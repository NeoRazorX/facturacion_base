/**
 * Created by ggarcia on 10/10/2016.
 */

$(function() {
    if($('input[name="concepto"]').val() != 'Ingreso') {
        $('#importar_caja_col').hide();
    }
    $('#sel_concepto').on('change', function(e) {
        if(this.value == 'Ingreso') {
            $('#importar_caja_col').show();
        } else {
            $('#importar_caja_col').hide();
        }
    });

    $('#importar_caja').on('click', function (e) {
        $('#modal_importar_caja .modal-body').append('Cargando!');
        $('#modal_importar_caja').modal();
        $('#modal_importar_caja .modal-body').load('index.php?page=importar_caja', function() {
            $('#action_importar_caja')
                .removeAttr('disabled')
                .on('click', function (e) {
                    $('#input_importar_caja').attr('value', 'true');
                    $('#f_asiento').submit();
                });
        });
        e.preventDefault();
        return false;
    });
});