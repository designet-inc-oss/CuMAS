function viewMail(id) {
    document.detail.selectedMail.value = id;
    document.detail.submit();
}

function sortMail() {
    // +1: oldest is first, -1: reverse
    document.detail.sort.value *= -1;
    document.detail.submit();
}

function detach() {
    var f = document.forms['detail'];

    var input = document.createElement('input');
    input.setAttribute('type', 'hidden');
    input.setAttribute('name', 'detach');
    input.setAttribute('value', '1');

    f.appendChild(input);
    f.submit();
}

function join() {
    var detail = document.forms['detail'];

    var to = document.createElement('input');
    to.setAttribute('type', 'hidden');
    to.setAttribute('name', 'joinTo');
    to.setAttribute('value', document.join.joinTo.value);

    var button = document.createElement('input');
    button.setAttribute('type', 'hidden');
    button.setAttribute('name', 'joinButton');
    button.setAttribute('value', '1');

    detail.appendChild(to);
    detail.appendChild(button);
    detail.submit();
}

function downloadFile(id)
{
    var form = document.createElement('form');
    var input1 = document.createElement('input');
    var input2 = document.createElement('input');

    input1.setAttribute('type', 'hidden');
    input1.setAttribute('name', 'download');
    input1.setAttribute('value', '1');
    input2.setAttribute('type', 'hidden');
    input2.setAttribute('name', 'target_at_id');
    input2.setAttribute('value', id);

    form.setAttribute('action', 'contact_detail.php');
    form.setAttribute('method', 'POST');

    document.body.appendChild(form);
    form.appendChild(input1);
    form.appendChild(input2);
    form.submit();
}

function sendMail(id)
{
    var action;
    var form = document.createElement('form');
    document.body.appendChild(form);

    if (typeof id === "undefined") {
        /* $('#myModal').modal('show'); */
        action = "contact_detail.php";
        var input1 = document.createElement('input');
        input1.setAttribute('type', 'hidden');
        input1.setAttribute('name', 'sendmail');
        input1.setAttribute('value', true);
        form.appendChild(input1);
    } else {
        action = "sendmail.php";
    }

    var input2 = document.createElement('input');
    input2.setAttribute('type', 'hidden');
    input2.setAttribute('name', 'selectedMail');
    input2.setAttribute('value', id);

    form.setAttribute('action', action);
    form.setAttribute('method', 'POST');

    form.appendChild(input2);

    form.submit();
}
