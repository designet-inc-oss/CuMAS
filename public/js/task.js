function removeCurrentCss()
{
    document.getElementById("mode-noloop").style.removeProperty('display');
    document.getElementById("mode-week").style.removeProperty('display');
    document.getElementById("mode-month").style.removeProperty('display');
    document.getElementById("mode-endmonth").style.removeProperty('display');

    document.getElementById("label-mode-noloop").style.removeProperty('display');
    document.getElementById("label-mode-week").style.removeProperty('display');
    document.getElementById("label-mode-month").style.removeProperty('display');
    document.getElementById("label-mode-endmonth").style.removeProperty('display');
}

function changeLoopMode(mode)
{
    if(mode == 3) {
        $("#datetimepicker").datetimepicker("setEndDayOfMonthOnlyEnable", 1);
    } else {
        $("#datetimepicker").datetimepicker("setEndDayOfMonthOnlyEnable", 2);
    }
}

function getStartDate()
{
    var nowdate = new Date();
    nowdate.setHours(0);
    nowdate.setMinutes(0);
    nowdate.setSeconds(0);
    return nowdate;
}

function confimr_delete(id)
{
    document.getElementById('targetid').value = id;
    $('#confirmModal').modal("show");
}

function pageJump(page) {
    document.pageStatus.pageNum.value = page;
    document.pageStatus.submit();
}

function toDetail(id) {
    document.pageStatus.selectedJob.value = id;
    document.pageStatus.submit();
}

function toUpdate(id) 
{
    var form = document.createElement('form');
    var input1 = document.createElement('input');
    var input2 = document.createElement('input');

    input1.setAttribute('type', 'hidden');
    input1.setAttribute('name', 'update');
    input1.setAttribute('value', '1');
    input2.setAttribute('type', 'hidden');
    input2.setAttribute('name', 'targetid');
    input2.setAttribute('value', id);

    form.setAttribute('action', 'task_mod.php');
    form.setAttribute('method', 'POST');

    document.body.appendChild(form);
    form.appendChild(input1);
    form.appendChild(input2);

    form.submit();
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
