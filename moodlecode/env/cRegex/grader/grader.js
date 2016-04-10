// Grader file for testing regexes

var readline = require('readline');
var fs = require('fs');

var model = require(process.argv[2]);

var rl = readline.createInterface({
    input: process.stdin,
    output: process.stdout,
    terminal: false
});

var numTotal = model.length;

if(numTotal == 0) {
    console.log("feedback: Grade file is empty");
    process.exit(1);
}

var results = "";

rl.on('line', function(line){
    results += line + "\n";
}).on('close', function() {
    for(i in model) {
        try {
            if(model[i].regex.length != 0 && (new RegExp(model[i].regex, "gi")).test(results)) {
                console.log("score: " + model[i].fraction);
                return;
            }
        }
    }

    console.log("score: 0");
});
