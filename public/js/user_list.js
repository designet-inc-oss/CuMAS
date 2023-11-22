function modJump(us_id) {
    var hidden = document.createElement('input');
    hidden.setAttribute('type', 'hidden');
    hidden.setAttribute('name', 'modUser');
    hidden.setAttribute('value', us_id);

    var form = document.createElement('form');
    form.setAttribute('action', 'user_list.php');
    form.setAttribute('method', 'POST');

    document.body.appendChild(form);
    form.appendChild(hidden);
    form.submit();
}
