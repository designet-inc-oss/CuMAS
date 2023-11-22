function logout() {
    var input = document.createElement('input');
    input.setAttribute('type', 'hidden');
    input.setAttribute('name', 'logout');
    input.setAttribute('value', true);

    var form = document.createElement('form');
    form.setAttribute('action', window.location.pathname.split('/').pop());
    form.setAttribute('method', 'POST');

    document.body.appendChild(form);
    form.appendChild(input);

    form.submit();
}
