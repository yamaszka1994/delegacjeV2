<?php

namespace app\controllers;

use core\App;
use core\Utils;
use core\ParamUtils;
use core\Validator;
use app\forms\DelegationEditForm;

class DelegationEditCtrl {

    private $form; //dane formularza

    public function __construct() {
        //stworzenie potrzebnych obiektów
        $this->form = new DelegationEditForm();
    }

    // Walidacja danych przed zapisem (nowe dane lub edycja).
    public function validateSave() {
        //0. Pobranie parametrów z walidacją
        $this->form->id = ParamUtils::getFromRequest('id', true, 'Błędne wywołanie aplikacji');
        $this->form->distance = ParamUtils::getFromRequest('distance', true, 'Błędne wywołanie aplikacji');
        $this->form->startTime = ParamUtils::getFromRequest('startTime', true, 'Błędne wywołanie aplikacji');
        $this->form->endTime = ParamUtils::getFromRequest('endTime', true, 'Błędne wywołanie aplikacji');
        $this->form->cityFrom = ParamUtils::getFromRequest('cityFrom', true, 'Błędne wywołanie aplikacji');
        $this->form->cityTo = ParamUtils::getFromRequest('cityTo', true, 'Błędne wywołanie aplikacji');
        $this->form->personId = ParamUtils::getFromRequest('personId', true, 'Błędne wywołanie aplikacji');
        $this->form->carId = ParamUtils::getFromRequest('carId', true, 'Błędne wywołanie aplikacji');

        if (App::getMessages()->isError())
            return false;

        // 1. sprawdzenie czy wartości wymagane nie są puste
        if (empty(trim($this->form->distance))) {
            Utils::addErrorMessage('Wprowadź dystans');
        }
        if (empty(trim($this->form->startTime))) {
            Utils::addErrorMessage('Wprowadź datę wyjazdu');
        }
        if (empty(trim($this->form->endTime))) {
            Utils::addErrorMessage('Wprowadź datę przyjazdu na miejsce');
        }
        if (empty(trim($this->form->cityFrom))) {
            Utils::addErrorMessage('Wprowadź miejsce początkowe');
        }
        if (empty(trim($this->form->cityTo))) {
            Utils::addErrorMessage('Wprowadź miejsce końcowe');
        }
        if (empty(trim($this->form->personId))) {
            Utils::addErrorMessage('Wprowadź pracownika');
        }
        if (empty(trim($this->form->carId))) {
            Utils::addErrorMessage('Wprowadź pojazd');
        }

        if (App::getMessages()->isError())
            return false;

        // 2. sprawdzenie poprawności przekazanych parametrów
        $check_distance = is_numeric($this->form->distance);
        if ($check_distance === false) {
            Utils::addErrorMessage('Dystans musi być liczbą');
        }

        //sprawdzenie poprawnosci daty

        $sT = $this->form->startTime;
        $eT = $this->form->endTime;

        $check_timeStart = checkdate(substr($sT, 5, 2), substr($sT, 8, 2), substr($sT, 0, 4));
        $check_timeEnd = checkdate(substr($eT, 5, 2), substr($eT, 8, 2), substr($eT, 0, 4));

        if ($check_timeEnd === false) {
            Utils::addErrorMessage('Zły format daty. Przykład: 2018-01-01 00:00:00 lub czeski błąd');
        }

        if ($check_timeStart === false) {
            Utils::addErrorMessage('Zły format daty. Przykład: 2018-01-01 00:00:00 lub czeski błąd');
        }

        return !App::getMessages()->isError();
    }

    //validacja danych przed wyswietleniem do edycji
    public function validateEdit() {
        //pobierz parametry na potrzeby wyswietlenia danych do edycji
        //z widoku listy osób (parametr jest wymagany)
        $this->form->id = ParamUtils::getFromCleanURL(1, true, 'Błędne wywołanie aplikacji');
        return !App::getMessages()->isError();
    }

    public function action_delegationNew() {
        $this->generateView();
    }

    //wysiweltenie rekordu do edycji wskazanego parametrem 'id'
    public function action_delegationEdit() {
        // 1. walidacja id osoby do edycji
        if ($this->validateEdit()) {
            try {
                // 2. odczyt z bazy danych osoby o podanym ID (tylko jednego rekordu)
                $record = App::getDB()->get("delegation", "*", [
                    "id" => $this->form->id
                ]);

                if (\core\RoleUtils::inRole('user')) {
                    if($record['person_id'] != App::getConf()->user['id']){
                        Utils::addInfoMessage('Możesz edytować tylko własne delegacje.');
                        App::getRouter()->forwardTo('delegationList');
                    }
                }

                // 2.1 jeśli osoba istnieje to wpisz dane do obiektu formularza
                $this->form->id = $record['id'];
                $this->form->distance = $record['distance'];
                $this->form->startTime = $record['start_time'];
                $this->form->endTime = $record['end_time'];
                $this->form->cityFrom = $record['city_from'];
                $this->form->cityTo = $record['city_to'];
                $this->form->personId = $record['person_id'];
                $this->form->carId = $record['car_id'];
            } catch (\PDOException $e) {
                Utils::addErrorMessage('Wystąpił błąd podczas odczytu rekordu');
                if (App::getConf()->debug)
                    Utils::addErrorMessage($e->getMessage());
            }
        }

        // 3. Wygenerowanie widoku
        $this->generateView();
    }

    public function action_delegationDelete() {
        // 1. walidacja id osoby do usuniecia
        if ($this->validateEdit()) {

            try {
                // 2. usunięcie rekordu
                App::getDB()->delete("delegation", [
                    "id" => $this->form->id
                ]);
                Utils::addInfoMessage('Pomyślnie usunięto rekord');
            } catch (\PDOException $e) {
                Utils::addErrorMessage('Wystąpił błąd podczas usuwania rekordu');
                if (App::getConf()->debug)
                    Utils::addErrorMessage($e->getMessage());
            }
        }

        // 3. Przekierowanie na stronę listy osób
        App::getRouter()->forwardTo('delegationList');
    }

    public function action_delegationSave() {

        // 1. Walidacja danych formularza (z pobraniem)
        if ($this->validateSave()) {
            // 2. Zapis danych w bazie
            try {
                if (\core\RoleUtils::inRole('user')) {
                    if($this->form->personId != App::getConf()->user['id']){
                        Utils::addInfoMessage('Możesz edytować tylko własne delegacje.');
                        App::getRouter()->forwardTo('delegationList');
                    }
                }

                //2.1 Nowy rekord
                if ($this->form->id == '') {
                    App::getDB()->insert("delegation", [
                        "distance" => $this->form->distance,
                        "start_time" => $this->form->startTime,
                        "end_time" => $this->form->endTime,
                        "city_from" => $this->form->cityFrom,
                        "city_to" => $this->form->cityTo,
                        "person_id" => $this->form->personId,
                        "car_id" => $this->form->carId
                    ]);
                } else {
                    //2.2 Edycja rekordu o danym ID
                    App::getDB()->update("delegation", [
                        "distance" => $this->form->distance,
                        "start_time" => $this->form->startTime,
                        "end_time" => $this->form->endTime,
                        "city_from" => $this->form->cityFrom,
                        "city_to" => $this->form->cityTo,
                        "person_id" => $this->form->personId,
                        "car_id" => $this->form->carId
                            ], [
                        "id" => $this->form->id
                    ]);
                }
                Utils::addInfoMessage('Pomyślnie zapisano rekord');
            } catch (\PDOException $e) {
                Utils::addErrorMessage('Wystąpił nieoczekiwany błąd podczas zapisu rekordu');
                if (App::getConf()->debug)
                    Utils::addErrorMessage($e->getMessage());
            }

            // 3b. Po zapisie przejdź na stronę listy osób (w ramach tego samego żądania http)
            App::getRouter()->forwardTo('delegationList');
        } else {
            // 3c. Gdy błąd walidacji to pozostań na stronie
            $this->generateView();
        }
    }

    public function generateView() {
        //pobranie listy miast, osób oraz samochów dla selectów w formularzu
        $cities = App::getDB()->select('city', '*');
        $persons = App::getDB()->select('person', '*');
        $cars = App::getDB()->select('car', '*');

        App::getSmarty()->assign('cities', $cities);
        App::getSmarty()->assign('persons', $persons);
        App::getSmarty()->assign('cars', $cars);
        App::getSmarty()->assign('form', $this->form); // dane formularza dla widoku
        App::getSmarty()->display('DelegationEdit.tpl');
    }

}
