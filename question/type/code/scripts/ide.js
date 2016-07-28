"use strict";

$(function() {
    $(".moodlecode_editortabs").tabs();
    $(".moodlecode_btn_run").click(function(e) {
        e.preventDefault();
        if($(this).attr("disabled")) {
            // sometimes the event may trigger twice
            // so we stop it here
            return false;
        }

        var button = $(this);

        var block = button.closest(".ablock");
        var results = block.find(".moodlecode_results");
        var message = block.find(".moodlecode_error");
        var fields = {};
        var editors = {};
        block.find(".moodlecode_field_input").each(function() {
            var u = $(this);
            fields[u.data().field] = u.val ? u.val() : u.data().val;
            if(u.data().editorid) {
                editors[u.data().editorid] = u.data().filename;
            }
        });

        $.ajax(atob(block.find(".moodlecode_url").val()), {
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
                        for(var i in editors) {
                            if(!editors.hasOwnProperty(i)) {
                                continue;
                            }
                            var editor = eval("editor" + i);
                            editor.getSession().setAnnotations();
                        }
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
                                        column: x.column - 1,
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
                message.html(status + ": " + err + "<br>" + a.responseText).show();
            }
        });

        return false;
    });


    function parseErrors(results) {
        var text = results.join("\n");
        // this is for C
        var matchError = /(\w+\.\w+):(\d+):(\d+): ?(.*)/gi;

        var errors = [];
        var r;
        while(r = matchError.exec(text)) {
            errors.push({
                filename: r[1],
                line: r[2],
                column: r[3],
                text: r[4]
            });
        }

        return errors;
    }
});