function pageJump(page) {
    document.pageStatus.pageNum.value = page;
    document.pageStatus.submit();
}
function toDetail(id) {
    document.pageStatus.selectedJob.value = id;
    document.pageStatus.submit();
}

function reset() {
    var input = document.createElement('input');
    input.setAttribute('type', 'hidden');
    input.setAttribute('name', 'reset');
    input.setAttribute('value', '1');

    var form = document.createElement('form');
    form.setAttribute('action', 'contact_search_result.php');
    form.setAttribute('method', 'POST');

    document.body.appendChild(form);
    form.appendChild(input);

    form.submit();
}
