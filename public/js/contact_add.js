function modJump(ca_id) {
    var hidden = document.createElement('input');
    hidden.setAttribute('type', 'hidden');
    hidden.setAttribute('name', 'modTask');
    hidden.setAttribute('value', ca_id);

    var form = document.createElement('form');
    form.setAttribute('action', 'task_list.php');
    form.setAttribute('method', 'POST');

    document.body.appendChild(form);
    form.appendChild(hidden);
    form.submit();
}

$(function(){
    $("#contact-datetimepicker").datetimepicker({
        format: 'yyyy/mm/dd hh:ii',
        language: 'ja',
        todayBtn: true,
        autoclose: false,
    });
})
