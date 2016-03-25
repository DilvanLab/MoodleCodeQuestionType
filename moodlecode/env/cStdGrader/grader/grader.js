// Grader file for comparing outputs to templates

var readline = require('readline');
var fs = require('fs');

var model = fs.readFileSync(process.argv[2]).toString().split("\n").filter(function(v) {
    return v.trim().length != 0; // ignore empty lines
});

var rl = readline.createInterface({
    input: process.stdin,
    output: process.stdout,
    terminal: false
});

var numTotal = model.length;
var numCorrect = 0;

if(numTotal == 0) {
    console.log("Grade file is empty");
    process.exit(1);
}

rl.on('line', function(line){
    if(line.trim().length == 0) { // ignore empty lines
        return;
    }

    var correct = model.shift();
    if(line == correct) {
        numCorrect++;
    }
}).on('close', function() {
    console.log("score: " + (numCorrect/numTotal));
});
