function EnvEditor(textarea, editor, button, select) {
    this.textarea = textarea;
    this.button = button;
    this.editor = editor;
    this.selector = select;
    this.options = JSON.parse(this.textarea.val() == "" ? "{}" : this.textarea.val());

    if(!this.options["@testcases"]) {
        this.options["@testcases"] = 1;
    }

    this.textarea.val(JSON.stringify(this.options, null, 4));

    textarea.hide();
    editor.setTheme("ace/theme/chrome");

    this.session = this.editor.getSession();
    this.session.setMode("ace/mode/json");
    this.session.setValue(textarea.val());

    /*
     * Sync the contents of the ace editor and
     * the real textarea
     */
    this.sync = function () {
        this.textarea.val(this.session.getValue());
    };

    this.session.on('change', $.proxy(this.sync, this));

    this.openEditor = function () {
        if (this.uiEditor) {
            this.uiEditor.open();
        } else {
            this.uiEditor = new EnvEditorUI(this, this.selector.val());
            this.uiEditor.open();
        }
    };

    this.loadEnv = function (id, callback) {
        $.ajax(ajaxURL, {
            method: "GET",
            data: {
                env: id
            },
            dataType: "json",
            success: function (data) {
                callback(data);
            },
            error: function (xhr, status, error) {
                callback({
                    error: error
                });
            }
        });
    };

    this.changeSelector = function () {
        if (this.uiEditor) {
            this.uiEditor.destroy();
            this.uiEditor = null;
        }
    };

    this.setOptions = function(o) {
        this.options = o;
        this.session.setValue(JSON.stringify(o, null, 4));
    };

    this.debug = function() {
        $(".envCodeDebug").show();
    };

    this.button.click($.proxy(this.openEditor, this));
    this.selector.change($.proxy(this.changeSelector, this));
}

function EnvEditorUI(envEditor, envID) {
    this.container = $("#envoptionsedit");
    this.content = $("<div/>");
    this.container.append(this.content);
    this.editor = envEditor;
    this.envID = envID;

    this.load = function () {
        this.content.children().remove();
        this.content.append($("<div>", {
            class: "ui-state-highlight ui-corner-all",
            style: "padding: 10px"
        }).html("Loading definition"));
        var self = this;
        this.editor.loadEnv(this.envID, function (data) {
            self.content.children().remove();
            if (!data || data.error) {
                self.content.append($("<div>", {
                    class: "ui-state-error ui-corner-all",
                    style: "padding: 10px"
                }).html("Failed to load definition:<br><pre>" + JSON.stringify(data) + "</pre>"));
                return;
            }
            self.definition = data;
            self.createFields();
        });
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
        //this.container.dialog("open");
        this.container.show();
    };

    this.close = function () {
        //this.container.dialog("close");
        this.container.hide();
    };

    this.destroy = function () {
        this.content.children().remove();
    };

    this.buttonOK = function () {
        this.close();
    };

    this.formatDocker = function (action) {
        var str = "<strong>Run a Docker container:</strong><br>";

        str += "<strong>Image: </strong>" + action.image + "<br>";

        str += "<strong>Actions: </strong><br>";
        str += " - Copy " + action.copy + " to " + action.copyTo + "<br>";

        //str += "<strong>Run commands:</strong><br>";
        for(var i in action.commands) {
            if(typeof(action.commands[i]) == "string") {
                str += this.formatCommand(action.commands[i]);
            } else {
                var c = action.commands[i];
                if(c['if:and']) {
                    var k = [];
                    if(!c['if:and'].length) {
                        c['if:and'] = [c['if:and']];
                    }
                    c['if:and'].forEach(function(v) {
                        k.push(v.a + v.operation + v.b);
                    });
                    str += "<b>if (" + k.join(" and ") + " )</b>";
                }
                str += this.formatCommand(c.cmd);
            }
        }

        return str;
    };

    this.getActionText = function (action) {
        var str = "";
        if (typeof action == "string") {
            str += "Run command:" + this.formatCommand(action);
        } else if (action.type == "docker") {
            str += this.formatDocker(action);
        }

        return str;
    };

    this.formatCommand = function(command) {
        return "<pre>" + command + "</pre>";
    };

    this.getFields = function (makeDefault) {
        var fields = {};

        // add inputs
        for(var i in this.definition.inputs) {
            var o = this.definition.inputs[i];
            if(makeDefault) {
                fields[o.id] = $.extend(true, {}, o);
            } else {
                fields[o.id] = $.extend(true, {}, o, this.editor.options[o.id]);
            }
        }

        if(!makeDefault) {
            fields["@testcases"] = this.editor.options['@testcases'];
        }

        // generate special fields (@testcases, @i.weight, method

        for(var j = 0; j < this.editor.options['@testcases']; j++) {
            fields["@" + j] = {};
            var u = fields["@" + j];

            // weight
            u["weight"] =  $.extend(true, {}, !makeDefault ? (this.editor.options["@" + j] ? this.editor.options["@" + j].weight : {}): {}, {
                type: "text",
                id: "@" + j + ".weight",
                name: "Weight",
                desc: "A value greater than 0 representing how much this test case is worth"
            });

            if(!u['weight'].value && !makeDefault) {
                u['weight'].value = 1;
            }

            // add options
            for (var i in this.definition.options) {
                var o = this.definition.options[i];
                if (makeDefault) {
                    var k = $.extend(true, {}, o);
                } else {
                    var f = this.editor.options["@" + j];
                    if (f) {
                        var k = $.extend(true, {}, o, f[o.id]);
                    } else {
                        var k = $.extend(true, {}, o);
                    }
                }
                u[k.id] = k;
                k.id = "@" + j + "." + k.id;
            }
        }

        fields["testCasesMethod"] = $.extend(true, {}, !makeDefault ? this.editor.options["testCasesMethod"] : {}, {
            type: "select",
            id: "testCasesMethod",
            name: "Test case method",
            desc: "This determines how the testcases interact.",
            selected: "lowest", // the default value
            list: [
                {
                    label: "Lowest",
                    value: "lowest"
                },{
                    label: "Highest",
                    value: "highest"
                },{
                    label: "Weighted sum",
                    value: "weighted"
                }
            ]
        });

        return fields;
    };

    this.registerFields = function (fields) {
        var self = this;
        $(".envEditorField").each(function() {
            $(this).change(function() {
                if($(this).data("val")) {
                    self.setValue($(this).data("id"), $(this).data("field"), $(this).data("val"));
                } else {
                    self.setValue($(this).data("id"), $(this).data("field"), $(this).val());
                }

            })
        });
    };

    this.createFields = function () {

        // draw the editors
        var fields = this.getFields();
        console.log(fields);
        var testCaseCount = $("<input>", {
            type: "hidden",
            class: "envEditorField"
        }).data("id", "@testcases").data("field", "@testcases").val(this.editor.options["@testcases"]);
        this.content.append(testCaseCount);

        var self = this;

        var after = $("<div>");
        var before = $("<div>", {
            id: "moodlecodeEditorTabs"
        }).css("position", "relative");
        before.append($("<button>").css({
            height: "30px",
            position: "absolute",
            top: "0",
            right: "0",
            margin: "10px"
        }).html("New").click(function(e) {
            testCaseCount.val(parseInt(testCaseCount.val()) + 1);
            testCaseCount.change();
            self.destroy();
            self.createFields();
            e.preventDefault();
            return false;
        }));
        var tablist = $("<ul>");
        var tabcontent = [];
        before.append(tablist);

        for(var k = 0; k < this.editor.options["@testcases"]; k++) {
            tablist.append($("<li>").append($("<a>", {
                href: "#moodlecodeEditorTab-" + k
            }).html(k)));
            tabcontent[k] = $("<div>", {
                id: "moodlecodeEditorTab-" + k
            });
            before.append(tabcontent[k]);
        }

        for(var i in fields) {

            if(i.charAt(0) == "@") {
                if(typeof(fields[i]) != "object") {
                    continue;
                }
                for(var j in fields[i]) {
                    var f = fields[i][j];
                    try {
                        var result = Renderers[f.type](f, "output.value");
                    } catch(err) {
                        console.error(err);
                        continue;
                    }
                    var d = tabcontent[i.match(/^@([0-9]+)/)[1]];
                    if(typeof result == "string") {
                        d.append($("<div>", {
                            style: "margin-top: 20px; margin-bottom: 20px"
                        }).html(result));
                    } else {
                        d.append($("<div>", {
                            style: "margin-top: 20px; margin-bottom: 20px"
                        }).append(result));
                    }
                }

                continue;
            }

            var target = false;

            for(var j in this.definition.options) {
                if(this.definition.options[j].id == i) {
                    target = "output.value";
                    break;
                }
            }

            for(var j in this.definition.inputs) {
                if(this.definition.inputs[j].id == i) {
                    target = "default";
                    break;
                }
            }
            if(!target) {
                target = "";
                console.log("Can't resolve: " + i);
            }

            var result = Renderers[fields[i].type](fields[i], target);
            if(typeof result == "string") {
                after.append($("<div>", {
                    style: "margin-top: 20px; margin-bottom: 20px"
                }).html(result));
            } else {
                after.append($("<div>", {
                    style: "margin-top: 20px; margin-bottom: 20px"
                }).append(result));
            }
        }

        this.content.append(after);
        this.content.append(before.tabs());

        // draw call settings
        var callSettings = $("<div>", {
            class: "envCodeDebug"
        }).css({display: "none"});
        callSettings.append($("<div>").html(
            "<strong>Name: </strong>" + this.definition.name
        ));

        for(i in this.definition.action) {
            callSettings.append($("<h5>").html("Action id: " + i));
            var actionText = this.getActionText(this.definition.action[i]);
            callSettings.append($("<div>").html(
                actionText
            ));
            callSettings.append($("<hr>"));
        }

        this.content.append(callSettings);

        this.registerFields(fields);
        this.updateFields(fields);
        $(".envEditorField").change();
    };

    // takes the current field values and puts them on the elements
    this.updateFields = function(fields) {
        console.log("Update fields", fields);
        var self = this;
        $(".envEditorField").each(function() {
            var id = $(this).data("id");
            var val = self.getValue(fields, id);
            var parts = $(this).data("field").split(".");
            var field = fields;
            while(parts.length > 0) {
                field = field[parts.shift()];
            }
            if(field && field.encode) {
                val = self.decode(val, field.encode);
            }
            if(val === null && $(this).data("default") != undefined) {
                val = $(this).data("default");
            }
            console.log($(this).data("id") + " Change value to " + val);
            $(this).val(val);
        });
    };

    this.encode = function(value, method) {
        if(!value) {
            return "";
        }

        if(method == "base64") {
            return Base64.encode(value);
        }

        return value;
    };

    this.decode = function(value, method) {
        if(!value) {
            return "";
        }

        if(method == "base64") {
            return Base64.decode(value);
        }

        return value;
    };

    // reduces o to a minimal diff from defaults
    this.minimal = function(o, defaults) {
        if(!defaults || typeof o != typeof defaults) {
            return o;
        }

        var obj = {};
        for(var i in o) {
            if(typeof o[i] == "object") {
                var on = this.minimal(o[i], defaults[i]);
                if(on && Object.keys(on).length) {
                    obj[i] = on;
                }
            } else {
                if(defaults[i] != o[i]) {
                    obj[i] = o[i];
                }
            }
        }

        return obj;
    };

    // removes everything from fields that is default
    this.minimalOptions = function (fields) {
        var defaults = this.getFields(true);
        return this.minimal(fields, defaults);
    };

    // encodes stuff and saves to the options
    this.setValue = function(id, fieldid, value) {
        var fields = this.getFields();
        var pieces = id.split(".").reverse();
        var arr = fields;
        while (pieces.length > 1) {
            var k = pieces.pop();
            if(!arr[k]) {
                arr[k] = {};
            }
            arr = arr[k];
        }

        var k = pieces.pop();

        var field = this.getValue(fields, fieldid);

        if(field.encode) {
            value = this.encode(value, field.encode);
        }
        if(field.parse) {
            value = JSON.parse(value);
        }

        arr[k] = value;
        this.editor.setOptions(this.minimalOptions(fields));

        //g("set", id, "to", value);
    };

    this.getValue = function(fields, id) {
        var pieces = id.split(".").reverse();
        var arr = fields;
        while (pieces.length) {
            var k = pieces.pop();
            if(!arr[k]) {
                return null;
            }
            arr = arr[k];
        }

        return arr;
    };

    this.load();
    //this.createDialog();
}

var langs = ["c_cpp", "python"];

var Renderers = {
    "textarea": function (x, target) {
        var str = "";

        //add title
        str += "<h4>" + x.name + "</h4>";

        if(x.desc) {
            str += "<div>" + x.desc + "</div><br>"
        }

        // add text area
        str += '<textarea style="width: 100%; height: 200px" data-field="' + x.id + '"  class="envEditorField" data-id="' + x.id + '.' + target + '"></textarea>';

        if(!x.nofile) {
            // output name and type
            str += "<strong>Output:</strong> ";
            str += '<select class="envEditorField" data-field="' + x.id + '.output" data-id="' + x.id + '.output.type"><option value="file">file</option><option value="std">stdIn/out</option></select>';
            str += '<input style="width: 300px;" type="text" data-field="' + x.id + '.output" class="envEditorField" data-id="' + x.id + '.output.name">';
        }

        return str;
    },
    "editor": function (x, target) {
        // make a deep copy
        x = $.extend(true, {}, x);
        if(!x.name) {
            x.name = "%" + x.id;
        }

        var str = Renderers["textarea"](x, target);
        /*str += "<br><strong>Lang:</strong> <select data-field='" + x.id + ".lang' data-id='" + x.id + ".lang' class=\"envEditorField\">";
        for(var i in langs) {
            if(langs[i]) {
                str += "<option value='" + langs[i] + '\'>' + langs[i] + "</option>";
            } else {
                str += "<option value=''></option>";
            }
        }

        str += "</select>";*/

        return str;
    },
    "regexlist": function (x, target) {
        var container = $("<div>", {
            "class": "regexinputs"
        });

        function change() {
            var target = $(this).closest(".regexinputs");
            var results = [];
            var response = target.find(".envEditorField");
            target.find(".regexgroup").each(function() {
                var group = $(this);
                results.push({
                    fraction: group.find(".regexfraction").eq(0).val(),
                    regex: group.find(".regex").eq(0).val()
                })
            });

            console.log(results);

            response.data("val", results);

            response.change();
        }

        container.append($("<h3>").text(x.name));
        if(x.desc) {
            container.append($("<div>").text(x.desc));
        }
        container.append($("<span>").html("Test Regexes on <a href='https://regex101.com/'>https://regex101.com/</a><br><br>"));

        var num = x.count || 4;
        //for(var i = 0; i < num; i++) {
        //    container.append($("<div>", {
        //        "class": "regexgroup"
        //    }).append([
        //        $("<label>").text("Fraction (0-1): ").append($("<input>", {
        //            type: "text",
        //            "class": "regexfraction"
        //        }).change(change)),
        //        $("<label>").text(" Regex: ").append($("<input>", {
        //            type: "text",
        //            "class": "regex"
        //        }).change(change))
        //    ]));
        //}

        for(var i = 0; i < num; i++) {
            container.append($("<div>", {
                "class": "regexgroup"
            }).append([
                $("<label>").html("Fraction (0-1): ").append($("<input>", {
                    type: "text",
                    "class": "envEditorField regexfraction",
                    style: "margin-right: 20px"
                }).data("field", x.id + ".output").data("id", x.id + ".output.value." + i + ".fraction").data("default",(num - i)/num)),
                $("<label>").html(" Regex: ").append($("<input>", {
                    type: "text",
                    "class": "envEditorField regex"
                }).data("field", x.id + ".output").data("id", x.id + ".output.value." + i + ".regex"))
            ]));
        }

        //container.append($("<input>", {
        //    type: "hidden",
        //    "class": "envEditorField"
        //}).data("field", x.id + ".output").data("id", x.id));

        return container;
    },
    "select": function(x, target) {
        var str="";
        //add title
        str += "<h4>" + x.name + "</h4>";

        if(x.desc) {
            str += "<div>" + x.desc + "</div><br>"
        }

        // add select
        str += "<select class='envEditorField' data-id='" + x.id + ".value' data-field='" + x.id + "'>";
        x.list.forEach(function(v) {
            str += "<option value='" + v.value + "' " + (x.selected == v.value ? "seleced" : "") + ">" + v.label + "</option>";
        });
        str += "</select>";
        return str;
    },
    "textselect": function(x, target) {
        var str = "";

        //add title
        str += "<h4>" + x.name + "</h4>";

        if(x.desc) {
            str += "<div>" + x.desc + "</div><br>"
        }

        // add select
        str += "<select class='envEditorField' data-id='" + x.id + ".selected' data-field='" + x.id + "'>";
        x.list.forEach(function(v) {
            str += "<option value='" + v.value + "' " + (x.selected == v.value ? "seleced" : "") + ">" + v.label + "</option>";
        });
        str += "</select>";

        str += "<input class='envEditorField' type='text' data-id='" + x.id + ".value' data-field='" + x.id + "'>";

        return str;
    },
    "text": function(x) {
        var str="";
        //add title
        str += "<h4>" + x.name + "</h4>";
        if(x.desc) {
            str += "<div>" + x.desc + "</div><br>"
        }
        // add select
        str += "<input class='envEditorField' type='text' data-id='" + x.id + ".value' data-field='" + x.id + "'>";
        return str;
    }
};

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

    this.encode = function () {
        this.base64toggle.val(Base64.encode(this.base64toggle.val()));
    };

    this.decode = function () {
        this.base64toggle.val(Base64.decode(this.base64toggle.val()));
    };


    this.createDialog();

    this.button.click($.proxy(this.open, this));
}

var envEditor;
var utils;
$(document).ready(function () {
    envEditor = new EnvEditor($("#envoptions"), ace.edit("envoptionsace"), $("#editenv"), $("#envselect"));
    //utils = new Utilities($("#envutils"));
    $("#envutils").click(function() {
        envEditor.debug();
        $("#envoptionsace").show();
    });
});