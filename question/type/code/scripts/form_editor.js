function EnvEditor(textarea, editor, button, select) {
    this.textarea = textarea;
    this.button = button;
    this.editor = editor;
    this.selector = select;

    textarea.hide();
    editor.setTheme("ace/theme/chrome");

    this.session = this.editor.getSession();
    this.session.setMode("ace/mode/json");
    this.session.setValue(textarea.val());

    /**
     * Sync the contents of the ace editor and
     * the real textarea
     */
    this.sync = function () {
        this.textarea.val(this.session.getValue());
    };

    this.session.on('change', $.proxy(this.sync, this));

    this.openEditor = function () {
        if (this.uiEditor) {
            this.uiEditor.destroy();
        }
        this.uiEditor = new EnvEditorUI(this);
        this.uiEditor.open();
    };

    this.button.click($.proxy(this.openEditor, this));
}

function EnvEditorUI(envEditor) {
    this.container = $("<div/>");
    this.content = $("<div/>");
    this.container.append(this.content);
    this.editor = envEditor;

    this.load = function () {

    };

    this.createDialog = function () {
        this.container.dialog({
            autoOpen: false,
            buttons: [
                {
                    text: "OK",
                    icons: {
                        primary: "ui-icon-check"
                    },
                    click: $.proxy(this.buttonOK, this)
                },
                {
                    text: "Cancel",
                    icons: {
                        primary: "ui-icon-closethick"
                    },
                    click: $.proxy(this.close, this)
                }
            ],
            minWidth: 500,
            minHeight: 500,
            modal: true
        });
    };

    this.open = function () {
        this.container.dialog("open");
    };

    this.close = function () {
        this.container.dialog("close");
    };

    this.destroy = function () {
        this.container.remove();
    };

    this.buttonOK = function () {
        this.close();
    };

    this.load();
    this.createDialog();
}

function Utilities(button) {
    this.button = button;
    this.container = $("<div/>");
    this.content = $("<div/>");
    this.container.append(this.content);
    this.base64toggle = $("<textarea>");
    this.container.append(this.base64toggle);
    this.base64toggle.css({
        width: "100%",
        height: "300px"
    });

    this.createDialog = function () {
        this.container.dialog({
            autoOpen: false,
            buttons: [
                {
                    text: "From Base64",
                    icons: {
                        primary: "ui-icon-mail-open"
                    },
                    click: $.proxy(this.decode, this)
                },
                {
                    text: "To Base64",
                    icons: {
                        primary: "ui-icon-mail-closed"
                    },
                    click: $.proxy(this.encode, this)
                },
                {
                    text: "Close",
                    icons: {
                        primary: "ui-icon-closethick"
                    },
                    click: $.proxy(this.close, this)
                }
            ],
            minWidth: 500,
            minHeight: 500,
            modal: false
        });
    };

    this.open = function () {
        this.container.dialog("open");
    };

    this.close = function () {
        this.container.dialog("close");
    };

    this.destroy = function () {
        this.container.remove();
    };

    this.encode = function() {
        this.base64toggle.val(btoa(encodeURIComponent(this.base64toggle.val())
            .replace(/%([0-9A-F]{2})/g, function(match, p1) {
                return String.fromCharCode('0x' + p1);
            }
        )));
    };

    this.decode = function() {
        this.base64toggle.val(atob(this.base64toggle.val()));
    };


    this.createDialog();

    this.button.click($.proxy(this.open, this));
}

var envEditor;
var utils;
$(document).ready(function () {
    envEditor = new EnvEditor($("#envoptions"), ace.edit("envoptionsace"), $("#editenv"), $("#envselect"));
    utils = new Utilities($("#envutils"));
});