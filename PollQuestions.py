'''
Generic Poll Questions
1.Did you know anything about this beforehand? (y/n)
2.Did this make you change your mind about the matter at hand? (y/n)
3.Do you agree with the author? (strongly agree, agree, don't know, disagree, strongly disagree)
4.Was this closely relevant to you? (y/n)
'''
Q1 = "1:Did you know anything about this beforehand?"
Q2 = "2:Did this make you change your mind about the matter at hand?"
Q3 = "3:Do you disagree with any one side? How much if so?"
Q4 = "4:Was this closely relevant to you?"

op1 = ("green:Yes","red:No")
op2 = ("green:Strongly disagree", "red:Disagree", "orange:Not sure","yellow:I didn't disagree with any side")
op3 = ("green:Very relevant", "orange:Moderately relevant", "red:Not relevant")

QuestionOption = {Q1:op1,Q2:op1,Q3:op2,Q4:op3}

Poll = { }

def toll_poll_results(debateId):

    x = [ ]
    for iterate in range(4): x += [[0]*4]
    x[0] = [0,0]
    x[1] = [0,0]
    x[2] = [0,0,0,0]
    x[3] = [0,0,0]

    Poll[debateId] = x

def load(debateId):
    import os
    if not os.path.exists('polls/' + debateId + '.txt'):
        return False
    f = open('polls/' + debateId + '.txt')
    data = eval(f.read())
    f.close()
    Poll[debateId] = data
    return True

def save(debateId):
    import os
    if not os.path.exists('polls'):
        os.mkdir('polls')
    f = open('polls/' + debateId + '.txt', 'w')
    f.write(str(Poll[debateId]))
    f.close()

def get_results(debateId):
    load(debateId)
    import json
    print json.dumps(Poll[debateId])

def do_vote(debateId, question, choice):
    question = int(question)
    choice = int(choice)
    load(debateId)
    Poll[debateId][question][choice] += 1
    save(debateId)
    print 'Success'

def create(debateId):
    if load(debateId):
        print 'Already created'
        exit()
    toll_poll_results(debateId)
    save(debateId)
    print 'Success'

def get_questions():
    import json
    print json.dumps(QuestionOption)

## Do not touch! This makes php (and others languages) able to call functions
## in this file
if __name__ == '__main__':
    import sys
    if len(sys.argv) < 2:
        exit()
    fn = sys.argv[1]
    args = sys.argv[2:] if len(sys.argv) > 2 else []
    globals()[fn](*args)
