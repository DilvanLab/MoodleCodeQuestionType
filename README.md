# What is the code question type?
`qtype_code` is a question type that autocorrects source code based on some settings. The question creator can select an environment in which the student's code will be run, as well as env-specific settings. Feedback can be customized by the grader application, and any programming language is supported.

The environment system is very powerful and robust, being able to support several types of correction methods. Compiling and running can be done inside a [`docker`](https://www.docker.com/) container, being completely isolated from the host.

This question type also uses Moodle's logger, creating an entry every time some code is executed. The content of the logger can be customized by the environment.

# Getting started
## Installing
1. Extract the source code into your Moodle folder.
2. Create the directory `/var/moodlecode/env` and make it writable by your apache user.
3. (Optional but recommended) Change the value of `env::$secret` on `env.class.php` to a random alphanumeric string
4. [Create an environment](https://github.com/DilvanLab/MoodleCodeQuestionType/wiki/Environment-Configuration)
