// Renames main() to _student_main()

var content = '';
process.stdin.setEncoding('utf8');

process.stdin.on('readable', () => {
    var chunk = process.stdin.read();
    if (chunk !== null) {
        content += chunk.toString();
    }
});

process.stdin.on('end', function() {
    content = content.replace(/(?:int) *main\(/, "int _student_main(");
    console.log(content);
});
