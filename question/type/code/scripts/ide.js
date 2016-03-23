"use strict";

$(function() {
    $("#moodlecode_editortabs").tabs();
    $("#moodlecode_btn_run").click(function(e) {
        e.preventDefault();

        var button = $(this);
        var results = $("#moodlecode_results");
        var message = $("#moodlecode_error");
        var fields = {};
        var editors = {};
        $(".moodlecode_field_input").each(function() {
            var u = $(this);
            fields[u.data().field] = u.val ? u.val() : u.data().val;
            if(u.data().editorid) {
                editors[u.data().editorid] = u.data().filename;
            }
        });

        $.ajax(atob($("#moodlecode_url").val()), {
            data: fields,
            method: 'POST',
            beforeSend: function() {
                button.attr('disabled', true);
                message.hide();
                results.text("Running code...");
            },
            complete: function() {
                button.attr("disabled", false);
            },
            success: function(data) {
                if(data.error) {
                    message.html(data.error.message).fadeIn();
                } else {
                    if(data.results) {
                        results.html(data.results.feedback.join("\n"))
                        if(!data.results.success) {
                            var errors = parseErrors(data.results.feedback);
                            for(var i in editors) {
                                if(!editors.hasOwnProperty(i)) {
                                    continue;
                                }

                                var errorArray = [];

                                for (var ei in errors) {
                                    if(!errors.hasOwnProperty(ei)) {
                                        continue;
                                    }
                                    if(errors[ei].filename == editors[i]) {
                                        errorArray.push({
                                            line: errors[ei].line,
                                            text: errors[ei].text
                                        });
                                    }
                                }

                                var editor = eval("editor" + i);
                                editor.getSession().setAnnotations(errorArray.map(function(x) {
                                    return {
                                        row: x.line - 1,
                                        text: x.text,
                                        type: "error"
                                    }
                                }));

                            }

                        }
                    }
                }
            },
            error: function(a, status, err) {
                message.text(status + ": " + err).show();
            }
        });

        return false;
    });


    function parseErrors(results) {
        var text = results.join("\n");
        // this is for C
        var matchError = /(\w+\.\w+):(\d+):\d+: ?(.*)/gi;

        var errors = [];
        var r;
        while(r = matchError.exec(text)) {
            errors.push({
                filename: r[1],
                line: r[2],
                text: r[3]
            });
        }

        return errors;
    }
});