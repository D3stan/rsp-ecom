# Telegram Crypto Trading Bot - Code of Conduct

## 1. Programming and Team Principles

### 1.1 Core Programming Principles

1. **Write Clean, Readable Code**
   - Code is read more often than written
   - Use meaningful variable and function names
   - Follow PEP 8 style guidelines
   - Add comments for complex logic, not obvious code

2. **Design for Maintainability**
   - Follow SOLID principles
   - Program to interfaces, not implementations
   - Keep functions small and focused (single responsibility)
   - Avoid premature optimization

3. **Embrace Test-Driven Development**
   - Write tests BEFORE implementing features
   - Maintain minimum 85% code coverage
   - Tests are not optional - they are part of the deliverable
   - If it's not tested, it's broken

<!-- ### 1.2 Team Collaboration Principles

1. **Respect the Contracts**
   - Never modify agreed-upon interfaces without team consensus
   - Document any assumptions in your implementation
   - Provide mock implementations for other tracks ASAP

2. **Communicate Asynchronously**
   - Assume your teammates are in different time zones
   - Write detailed documentation and comments
   - Over-communicate rather than under-communicate

3. **Code Review Culture**
   - All code must be reviewed before merging
   - Be constructive in reviews
   - Accept feedback gracefully
   - Review promptly when requested -->

## 2. Supervisor Relations and Reporting

### 2.1 Communication with Supervisor

1. **Always Ask When in Doubt**
   - NEVER assume or take things for granted
   - It's better to ask a "stupid" question than make a wrong assumption
   - Questions save time and prevent rework
   - No question is too small

2. **Proactive Communication**
   - Report blockers immediately
   - Provide daily status updates
   - Flag risks or concerns early
   - Request clarification on ambiguous requirements

3. **Decision Documentation**
   - Document why you made specific technical decisions
   - If you deviate from the plan, explain why
   - Keep your supervisor informed of major changes

<!-- ### 2.2 Session Reporting Requirements

Every developer MUST maintain a personal reporting folder that is used ONLY for the purposes indicated below:

/reports/{developer-name}/
├── session-001-2024-01-15-telegram-client.md
├── session-002-2024-01-16-telegram-client.md
├── session-003-2024-01-17-message-processor.md
└── questions/
    ├── question-001-2024-01-15.json
    └── question-002-2024-01-17.json

Example structure:
/reports/
├── track1-dev/
│   ├── session-001-2024-01-15-telegram-client.md
│   ├── session-002-2024-01-16-message-processor.md
│   └── questions/
│       └── question-001-2024-01-15.json
├── track2-dev/
│   ├── session-001-2024-01-20-ai-analyzer.md
│   └── questions/
└── track3-dev/
    ├── session-001-2024-01-25-exchange-adapter.md
    └── questions/

**Session Report Format:**

# Session Report: {number} - {date}

## Module: {module-name}
## Duration: {hours}
## Branch: {branch-name}

### Objectives
- What I planned to accomplish

### Completed
- What I actually accomplished
- Files modified
- Tests written

### Challenges
- Problems encountered
- How they were resolved

### Next Steps
- What needs to be done next
- Any blockers

### Code Snippets
- Key implementations or changes

**Report Timing:**
- Create a new report when starting a different module/component
- Update the report after each coding session
- Maximum 3 sessions per report file -->

## 3. Testing Protocol

### 3.1 Testing is MANDATORY

- **No Exceptions**: Every function must have tests
- **Test First**: Write tests before implementation
- **Coverage Target**: Maintain 85% minimum coverage
- **Types Required**:
  - Unit tests for all functions
  - Integration tests for external interfaces
  - Mock tests for dependencies

<!-- ### 3.2 Partitioned Testing Strategy

To avoid conflicts between developers:

1. **Test Namespacing**
   # Track 1 tests
   tests/track1/test_telegram_client.py
   tests/track1/test_message_processor.py
   
   # Track 2 tests  
   tests/track2/test_ai_analyzer.py
   tests/track2/test_signal_extractor.py
   
   # Track 3 tests
   tests/track3/test_kraken_adapter.py
   tests/track3/test_risk_manager.py

2. **Mock Isolation**
   - Each track provides official mocks in mocks/trackX/
   - Never modify another track's mocks
   - Use only official mocks for cross-track testing

3. **Test Data Isolation**
   - Test data in tests/fixtures/trackX/
   - No shared mutable test data
   - Each track manages its own test database schemas -->

### 3.3 Continuous Testing

- Fix broken tests immediately
- Add tests for every bug fix


## 4. Shell Interaction

### 4.1 Banned Commands
You must not execute commands that are aimed to:
* installing libraries or dependencies
* running the server itself (composer run dev, npm run, etc)
* running DB commands (migrations, deletions etc)

Each time you find yourself needing to run these commands you should just report the supervisor why and how to run them.

<!-- ## 4. Inter-Developer Communication

### 4.1 Question Protocol

All questions between developers MUST use the following JSON format:

{
  "question_id": "Q-001-2024-01-15",
  "from_developer": "john_doe",
  "to_developer": "jane_smith",
  "track": "Track-1",
  "urgency": "high|medium|low",
  "category": "interface|implementation|clarification|bug",
  "subject": "Clear, specific subject line",
  "context": {
    "related_module": "telegram_client",
    "related_files": ["src/telegram/client.py"],
    "related_commit": "abc123"
  },
  "question": "Detailed question with all necessary context",
  "attempted_solutions": [
    "What I already tried",
    "Why it didn't work"
  ],
  "blocking": true,
  "deadline": "2024-01-16T15:00:00Z"
}

### 4.2 Question and Answer Workflow

**Asking Questions:**
1. **Create Question File**: Save in your questions/ folder using format: `question-001-YYYY-MM-DD.json`
2. **Notify Supervisor**: Report the question immediately in your communication
3. **Supervisor Routes**: Supervisor notifies target developer and coordinates response

**Answering Questions:**
1. **Answer Format**: Create comprehensive answer file in your answers/ folder
2. **Answer Structure**: Use detailed JSON format with technical analysis
3. **File Naming**: Use format: `answer-001-YYYY-MM-DD.json` matching question ID
4. **Response Content**: Include status assessments, technical compatibility, and next steps
5. **Documentation**: Both question and answer saved for future reference and knowledge sharing

**Updated Directory Structure:**
```
/reports/{developer-name}/
├── session-001-2024-01-15-module-name.md
├── questions/
│   ├── question-001-2024-01-15.json
│   └── question-002-2024-01-17.json
└── answers/
    ├── answer-001-2024-01-15.json
    └── answer-002-2024-01-17.json
```

**Answer Quality Standards:**
- Provide comprehensive technical analysis
- Include compatibility assessments  
- Specify required vs. optional modifications
- Document integration points and dependencies
- Include clear next steps and action items
- Assess confidence level and risk factors

### 4.3 Response Time Expectations

- **High Urgency**: Within 24 hours
- **Medium Urgency**: Within 48 hours  
- **Low Urgency**: Within 72 hours
- **Blocking Issues**: Escalate to supervisor immediately -->

## 5. Accountability and Consequences

### 5.1 Expected Behaviors

- Professional communication at all times
- Respect for teammates and their time
- Commitment to quality and deadlines
- Ownership of your code and its bugs

### 5.2 Unacceptable Behaviors

- Committing untested code
- Modifying contracts without approval
- Ignoring code review feedback
- Not reporting blockers promptly
- Making assumptions without asking

### 5.3 Escalation Process

1. First violation: Verbal warning and coaching
2. Second violation: Written warning
3. Third violation: Review with project lead
4. Continued violations: Removal from project

## 6. Remember

- Your code affects others - write it responsibly
- Communication prevents problems - use it liberally
- Tests are your safety net - never work without them
- When in doubt, ASK - it's always the right choice

By participating in this project, you agree to follow this Code of Conduct and maintain the highest standards of professional development.

Version: 1.0.2