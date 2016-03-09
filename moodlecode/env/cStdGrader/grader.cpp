#include <iostream>
#include <fstream>
#include <string>

using namespace std;

class MCLGrader {
	public:
		static void grade(float fraction) {
			cout << "score:" << fraction << endl;
		}
};

int main(int argc, char* argv[]) {
	int correct = 0;
	int total = 0;
	ifstream file;
	file.open(argv[1]);
	string lineExpected;
	string lineOutput;
	while(getline(file, lineExpected)) {
		total++;
		if(cin.eof() || (getline(cin, lineOutput), lineExpected != lineOutput)) {
			continue;
		} else {
			correct++;
		}
	}
	
	getline(cin, lineOutput);
	while(!cin.eof()) {
		total++;
		getline(cin, lineOutput);
	}

	MCLGrader::grade(((float) correct) / total);
	return 0;
}
