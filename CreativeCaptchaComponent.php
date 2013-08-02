<?php
/**
* CreativeCaptcha Component for CakePHP 2.
*
*/
class MathCaptchaComponent extends Component {

  public $components = array('Session');

  /**
  * Phrasing-choices for the captcha question.
  * @access private
  * @var array
  */
  private $choices = array(
    "What is the sum of .:addition-problem:.?", 
    "What does .:word-problem:. result in?", 
    "What is .:problem:.?", 
    "What's the result of .:word-problem:.?", 
    "What do you get when you .:word2-problem:.?" 
  ); 

  /**
  * Placeholders to be used in the question phrasing. Recursion enabled.
  * 
  * @internal 
  *
  * @access private
  * @var array
  */
  private $placeholders = array(
    ".:problem:." => ".:number:. .:operator:. .:number:.",
    ".:word-problem:." => array(
      ".:operatorword-ing:. .:number:." => false,
      ".:number:. .:operator:. .:number:." => false),

    ".:word2-problem:." => ".:operatorword:. .:number:.",
    ".:addition-problem:." => ".:number:. .:add:. .:number:.",


    ".:number:." => array(
      "0" => 0,
      "zero" => 0,
      "1" => 1,
      "one" => 1,
      "2" => 2,
      "two" => 2,
      "3" => 3,
      "three" => 3,
      "4" => 4,
      "four" => 4,
      "5" => 5,
      "five" => 5),

    ".:operator:." => array(
      "+" => "+",
      "plus" => "+",
      "added to" => "+",
      "times" => "*",
      "multiplied by" => "*"),
    
    ".:add:." => array(
      "and" => "+",
      "plus" => "+",
      "+" => "+"),

    ".:operatorword:." => array( 
      "add .:number:. to" => "+",
      "multiply .:number:. by" => "*"),

    ".:operatorword-ing:." => array(
      "adding .:number:. to" => "+",
      "multiplying .:number:. by" => "*")
  );

  /**
  * Answer alternatives to enable the user to reply in words OR numbers.
  *
  * @internal When modifying placeholders, make sure every possible result is
  *   covered in this property. To give more than one alternative make an array
  *   with alternative strings, for example to compensate for typos.
  *
  * @access private
  * @var array
  */
  private $alternatives = array(
    0  => array("zero", "O", "null", "nil", "nada", "zip", "zilch", "nothing", "rien", "naught"),
    1  => "one",
    2  => "two",
    3  => "three",
    4  => "four",
    5  => "five",
    6  => "six",
    7  => "seven",
    8  => "eight",
    9  => "nine",
    10 => "ten",
    12 => "twelve",
    15 => array("fifteen", "fivteen"),
    16 => "sixteen",
    20 => "twenty",
    25 => array("twentyfive", "twenty-five")
  );

  /**
  * The Captcha-Question.
  *
  * @access private
  * @var string
  */
  private $question;

  /**
  * The Operator of the equation.
  *
  * @access private
  * @var string
  */
  private $operator;

  /**
  * The first number in the equation.
  *
  * @access private
  * @var integer
  */
  private $firstnum;

  /**
  * The second number in the equation.
  *
  * @access private
  * @var integer
  */
  private $secondnum;

  /**
  * Default values for settings.
  *
  * @access private
  * @var array
  */
  private $__defaults = array(
    'timer' => 0,
    'godmode' => false,
    'tabsafe' => false
  );

    public function __construct($collection, $settings = array()) {
    parent::__construct($collection, $settings);
    $this->settings = array_merge($this->__defaults, $settings);
  } 
  public function reset() {
    $this->question = null;
    $this->operator = null;
    $this->firstnum = null;
    $this->secondnum = null;
  }
  public function makeCaptcha($autoRegister = true) {
    $this->reset();
    $question = $this->choices[mt_rand(0, count($this->choices)-1)];
    $this->convertPlaceholders($question);

    if ($autoRegister && !$this->settings['tabsafe']) $this->registerAnswer();

    return $this->question;
  }

  private function convertPlaceholders($string, $maxrepeat = -1, $repitition = 0) {
    foreach ($this->placeholders as $key => $value) {

      $pos = strpos($string, $key);
      if ($pos !== false) {

        if (is_array($value)) {
          $replace = array_rand($value); // if value is array, gets random key out of it
          $string = substr_replace($string, $replace, $pos, strlen($key)); 

          if ($this->setResultParams($value[$replace])) { // saves operators/numbers
            $this->question = $string;
            return;
          }
          
        } else { // $value is string
          $replace = $value;
          $string = substr_replace($string, $replace, $pos, strlen($key));
        }
      }
    }
    unset($value); // clears memory used by foreach

    // recursive function
    if ($maxrepeat < 0)
      $this->convertPlaceholders($string);
    else if ($maxrepeat >= 0 && $repitition < $maxrepeat)
      $this->convertPlaceholders($string, $maxrepeat, $repitition+1); 
  }

  /**
  * Sets the parameters necessary for calculating the result.
  *
  * @access private
  * @return bool Returns true when all parameters have been filled, otherwise false.
  */
  private function setResultParams($value) {
    if (is_string($value)) { 
      $this->operator = $value;
    } else if (is_numeric($value)) {
      if ($this->firstnum === null) {
        $this->firstnum = $value;
      } else {
        $this->secondnum = $value;
        return true;
      }
    }
    return false;
  }

  /**
  * Returns the result of the current MathCaptcha.
  *
  * @access public
  * @throws ErrorException "MathCaptcha generation failed" gets thrown in case
  *   the function gets called before all numbers and operators have been set.
  * @return int
  */
  public function getResult() {
    if ($this->firstnum === null|| $this->secondnum === null || $this->operator === null) 
      throw new ErrorException("MathCaptcha generation failed.");

    $result = null;
    switch ($this->operator) {
      case "+":
        $result = $this->firstnum + $this->secondnum;
        break;
      case "-":
        $result = $this->firstnum - $this->secondnum;
        break;
      case "*":
        $result = $this->firstnum * $this->secondnum;
        break;
      case "/":
        $result = $this->firstnum / $this->secondnum;
    }

    if ($this->settings['tabsafe'])
      return md5($result);
    else
      return $result;
  }

  /**
  * Returns the current captcha question string.
  *
  * Creates a new one and returns it if none exist.
  *
  * @access public
  * @return string The MathCaptcha Question.
  */
  public function getCaptcha() {
    if ($this->question)
      return $this->question;
    else {
      return $this->makeCaptcha();
    }
  }

 /**
  * Save Answer to Session.
  *
  * Registers the answer to the math problem and the timer (if applicable) as 
  * session variables.
  *
  * @access public
  * @return integer
  */
  public function registerAnswer() {
    $answer = $this->getResult();

    $this->Session->write('MathCaptcha.result', $answer);
    if ($this->settings['timer'] && !$this->Session->read('MathCaptcha.time'))
      $this->Session->write('MathCaptcha.time', time());

    return $answer;
  }

  /**
  * Deletes all set session variables of the MathCaptcha Component.
  *
  * This is useful to be able to restart the timer or to declutter the session.
  *
  * @access public
  * @return void
  */
  public function unsetAnswer() {
    $this->Session->delete('MathCaptcha.result');
    $this->Session->delete('MathCaptcha.time');
  }
  
  /**
  * MathCaptcha Validation
  *
  * Compares the given data to the registered equation answer and compensates if
  * the user typed in the number as a word.
  *
  * @access public
  * @param mixed $data The data that gets validated. When using tabsafe, give an array
  *   with [$user_answer, $resulthash]. Otherwise just the user's answer as integer or string.
  * @param bool $loose Whether or not to allow corresponding words as correct answers.
  * @param bool $autoUnset Automatically removes the Session vars if the validation ends up true.
  * @return bool
  */
  public function validate($data, $loose = true, $autoUnset = true) {
   
    if (is_array($data)) 
      $answer = $data[0];
    else
      $answer = $data;

    if ($this->settings['godmode'] && $answer == 42)
      return true;

    if ($this->settings['timer']) {
      if (($this->Session->read('MathCaptcha.time') + $this->settings['timer']) > time())
        return false;
    }

    if ($this->settings['tabsafe'])
      return $this->validateTabsafe($data, $loose, $autoUnset);
    else
      $result = $this->Session->read('MathCaptcha.result');

    $validated = ($data == $result);

    if ($loose && !$validated) {
      if (is_array($this->alternatives[$result])) {
        foreach ($this->alternatives[$result] as $alternative) {
          if (strcasecmp($data, $alternative) == 0) $validated = true;
        }
      } else {
        if (strcasecmp($data, $this->alternatives[$result]) == 0) $validated = true;
      }
    }

    if ($validated && $autoUnset) $this->unsetAnswer();
    return $validated;
  }

  /**
  * Tabsafe MathCaptcha Validation
  *
  * @ignore
  */
  private function validateTabsafe($data, $loose, $autoUnset) {
    $result = $data[1];
    $data = $data[0];

    $validated = (md5($data) == $result);

    if ($loose && !$validated) {
      foreach ($this->alternatives as $key => $value) {
        if ($result == md5($key)) {
          if (is_array($value)) {
            foreach ($value as $alternative) {
              if (strcasecmp(md5($data), md5($alternative)) == 0) $validated = true;
            }
          } else {
            if (strcasecmp(md5($data), md5($value)) == 0) $validated = true;
          }
        }
      }
    }

    if ($validated && $autoUnset) $this->unsetAnswer();
    return $validated;
  }
}
?>