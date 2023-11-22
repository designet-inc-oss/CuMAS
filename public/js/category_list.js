function modJump(ca_id) {
    var hidden = document.createElement('input');
    hidden.setAttribute('type', 'hidden');
    hidden.setAttribute('name', 'modCategory');
    hidden.setAttribute('value', ca_id);

    var form = document.createElement('form');
    form.setAttribute('action', 'category_list.php');
    form.setAttribute('method', 'POST');

    document.body.appendChild(form);
    form.appendChild(hidden);
    form.submit();
}
