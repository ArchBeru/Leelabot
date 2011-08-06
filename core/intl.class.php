<?php
/**
 * \file core/intl.class.php
 * \author Yohann Lorant <linkboss@gmail.com>
 * \version 0.5
 * \brief Internationalization class file.
 *
 * \section LICENSE
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * General Public License for more details at
 * http://www.gnu.org/copyleft/gpl.html
 *
 * \section DESCRIPTION
 *
 * This file hosts the Internationalization class, allowing the bot to talk in multiple languages and use multiple date formats.
 */

/**
 * \brief Internationalization class.
 * 
 * This class is the Internationalization class, allowing the bot to talk in different languages with ease and use different date formats
 * (in accordance with the related language). It reads locale data (messages, date format and alias) from configuration files, wrote with
 * a special syntax.
 */
class Intl
{
	private $_locale; ///< Current set locale.
	private $_root; ///< Locales root directory
	
	/** Constructor.
	 * This is the constructor for the class. It initalizes properties at their default values, and set the default locale if a parameter is
	 * given.
	 * 
	 * \return The object currently created. If the locale is not available, FALSE will be returned.
	 */
	public function __construct($locale = NULL)
	{
		//Setting default values for properties
		$this->_root = 'data/locales';
		
		if($locale)
		{
			if(!$this->setLocale($locale))
				return FALSE;
		}
	}
	
	/** Returns the current locale.
	 * This function returns the current locale set with the function setLocale, or, if not set, the default locale.
	 * 
	 * \return The current set locale.
	 */
	public function getLocale()
	{
		return $this->_locale;
	}
	
	/** Sets the class locale.
	 * This function sets the current locale to the specified one given as parameter. It also loads the locale from the configuration files.
	 * 
	 * \param $locale The locale to set.
	 * \return TRUE if the locale has been changed correctly, FALSE otherwise (specified locale does not exists, specified locale is corrupted...).
	 */
	public function setLocale($locale)
	{
		
	}
	
	/** Checks if a locale exists.
	 * This function checks if the locale exists, by its folder name, or by an alias 
	 * 
	 * \param $locale The locale to set.
	 * \return TRUE if the locale has been changed correctly, FALSE otherwise (specified locale does not exists, specified locale is corrupted...).
	 */
	public function localeExists($locale)
	{
		$dir = scandir(self::LOCALES_ROOT);
		foreach($dir as $el)
		{
			if(is_dir($el))
			{
				if($el == $locale) //Folder name check
					return $el;
				
				if(is_file(self::LOCALES_ROOT.'/'.$el.'/lc.conf')) //Alias check
				{
					$parser = new Intl_Parser();
				}
			}
		}
	}
}

/**
 * \brief Internationalization files parser.
 * 
 * This class is used along the Intl class, for parsing locales files and folders. Its only purpose is to parse locale config files, for
 * better code separation, and lighter memory usage (the parser doesn't have to be loaded when the files have not to be parsed).
 */
class Intl_Parser
{
	private $_data; ///< Data already parsed by the parser.
	
	/** Constructor.
	 * This is the constructor for the class. It initalizes properties at their default values.
	 * 
	 * \return The object currently created.
	 */
	public function __construct()
	{
		//Initializing properties
		$this->_data = array();
	}
	
	/** Parses a locale file.
	 * This function parses an unique locale file, for gathering all available data inside. Of course, if there is #include statements in
	 * the given file, included files will be also parsed. If there is an inclusion loop, this function will loop indefinitely, filling all
	 * the available memory, so beware of what you are parsing, and what you are including.
	 * 
	 * \param $file The file to parse.
	 * \return TRUE if the file has been correctly parsed, FALSE otherwise.
	 */
	public function parseFile($file)
	{
		if(!is_file($file))
			return FALSE;
		
		$content = file_get_contents($file);
		
		//Getting rid of comments
		$content = preg_replace("#/\*(.*)\*/#isU", '', $content);
		
		//Getting commands and looping them for individual parsing
		$commands = explode("\n", $content);
		foreach($commands as $command)
		{
			$result = $this->_parseCommand($command, $file);
			if($result !== FALSE)
				$this->_data = array_merge($this->_data, $result);
			else
				return FALSE;
		}
		
		return $this->_data;
	}
	
	/** Parses a command.
	 * This function simply parses a single command, stripping all parasites (like extra spaces and so) and interpreting the statements
	 * get.
	 * 
	 * \param $command The command to parse.
	 * \param $file The file from where the command comes from. It is useful for knowing the base path for the #include statement.
	 * \returns The data parsed (to be added to the global data array), or FALSE if anything has gone wrong.
	 */
	private function _parseCommand($command, $file)
	{
		if(!$command)
			return array();
		
		//Tabs will be interpreted as space, for splitting.
		$command = str_replace("\t", ' ', trim($command));
		list($keyword, $params) = explode(' ', $command, 2);
		
		//Keyword (i.e. command name) parsing
		switch($keyword)
		{
			//Same behavior for these commands, globally locale info
			case '#author':
			case '#contact':
			case '#version':
			case '#display':
			case '#license':
			case '#dateformat':
			case '#timeformat':
				return array(substr($keyword, 1) => $params);
				break;
			//Locale alias names
			case '#alias':
				if(strpos($params, ';') === FALSE) //Unique alias
				{
					if(isset($this->_data['aliases']))
						$aliases = array_merge($this->_data['aliases'], array($params));
					else
						$aliases = array($params);
				}
				else //Multiple aliases
				{
					if(isset($this->_data['aliases']))
						$aliases = array_merge($this->_data['aliases'], array_map('trim',explode(';',$params)));
					else
						$aliases = array_map('trim',explode(';',$params));
				}
				return array('aliases' => $aliases);
				break;
			//Include another files (relative paths from current read file path)
			case '#include':
				$dir = pathinfo($file, PATHINFO_DIRNAME);
				$result = $this->parseFile($dir.'/'.$params);
				//If parsing failed, chain
				if($result === FALSE)
					return FALSE;
				break;
		}
		
		return array();
	}
}
