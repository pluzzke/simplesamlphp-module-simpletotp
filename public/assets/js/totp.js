document.getElementById('code').addEventListener('paste', totpentered);
document.getElementById('code').addEventListener('keyup', totpentered);
document.getElementById('totpForm').addEventListener('submit', submit);

var submitted = false

function totpentered(e) {
    var value = document.getElementById('code').value;
    // console.log('totpentered', value, e);
    if (!value && e.constructor.name === 'ClipboardEvent') {
        var clipboardData = e.clipboardData || window.clipboardData;
        if (clipboardData) {
            value = clipboardData.getData('Text');
            document.getElementById('code').value = value;
            e.stopPropagation();
            e.preventDefault();
        }
        // console.log('pasted', value,value.length);
    }

    if (e.key === 'Enter') {
        console.log('enter key pressed');
        return true;
    } else if (value.length === 6) {
        // console.log('value.length === 6');
        if (submit()) {
            setTimeout(function () {
                document.getElementById('submitButton').click();
            }, 100);
        }
        return false;
    }

    return false;
}

function submit(e) {
    var value = document.getElementById('code').value;
    // console.log('submit', e,value,value.length, submitted);
    if (value.length !== 6 || submitted) {
        if (e) {
            e.stopPropagation();
            e.preventDefault();
        }
        return false;
    }

    if (e) {
        submitted = true;
    }
    return true;
}