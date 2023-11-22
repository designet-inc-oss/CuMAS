
function confirmSendmail()
{
    $('#cfgSendMail').modal("show");
}

function confirmClear()
{
    $('#cfgClear').modal("show");
}

function confirmCancel()
{
    $('#cfgCancel').modal("show");
}

function sendmail(name) 
{
    var f = document.forms['form-sendmail'];

    var input = document.createElement('input');
    input.setAttribute('type', 'hidden');
    input.setAttribute('name', name);
    input.setAttribute('value', '1');
    f.appendChild(input);
    f.submit();
}
