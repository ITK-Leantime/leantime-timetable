/**
 * Class handles API requests for time table data.
 */
const ticketCacheExpiration = document.getElementById(
  "timetable-ticketCacheExpiration",
).value;
export default class TimeTableApiHandler {
  static cacheTimeouts = {
    tickets: parseFloat(ticketCacheExpiration),
  };

  /**
   * Retrieves ticket data from cache or fetches it from the server.
   *
   * @returns {Promise<Array>} An array of ticket data.
   */
  static async fetchTicketData() {
    let projectPromise;
    let ticketPromise;

    let ticketCacheData = this.getCacheData("tickets");
    if (ticketCacheData) {
      ticketPromise = Promise.resolve(ticketCacheData);
    } else {
      ticketPromise = this.getAllTickets().then((data) => {
        const result = data.result;
        let tickets = result.filter(
          ({ type }) =>
            type.toLowerCase() !== "story" &&
            type.toLowerCase() !== "milestone",
        );
        const ticketGroup = {
          id: "task",
          text: "Todos",
          children: [],
          index: 2,
        };

        let childrenForTicketGroup = [];
        tickets.forEach((ticket) => {
          let option = {
            // status 0 is done, I found out through this commit message
            // https://github.com/ITK-Leantime/leantime/commit/122a08ea0cc61c65aa57fd1d73f0948d46744055
            isDone: ticket.status === 0,
            id: ticket.id,
            text: ticket.headline,
            type: ticket.type,
            tags: ticket.tags,
            sprintName: ticket.sprintName,
            projectId: ticket.projectId,
            projectName: ticket.projectName,
            hoursLeft: ticket.hourRemaining,
          };
          childrenForTicketGroup.push(option);
        });

        // Sort, so the done tasks appear in the bottom of the search.
        ticketGroup.children = [...childrenForTicketGroup].sort(
          (a, b) => Number(a.isDone) - Number(b.isDone),
        );
        this.writeToCache("tickets", {
          data: ticketGroup,
          expiration: Date.now(),
        });
        return ticketGroup;
      });
    }

    const promises = [projectPromise, ticketPromise];
    const results = await Promise.allSettled(promises);
    return results
      .filter((result) => result.status === "fulfilled")
      .map((result) => result.value)
      .sort(function (a, b) {
        // Sort by index.
        return a.index - b.index;
      });
  }

  /**
   * Retrieves a specific ticket data from cache or fetches it from the server.
   *
   * @param {number} ticketId The ID of the ticket to fetch.
   * @returns {Promise<Object>} An object of ticket data.
   */
  static async fetchTicketDatum(ticketId) {
    let ticketPromise;
    let ticketCacheData = this.getCacheData("tickets");
    ticketPromise = this.getTicket(ticketId).then((ticket) => {
      ticket = ticket.result;
      const ticketData = {
        isDone: ticket.status === 0,
        id: ticket.id,
        text: ticket.headline,
        type: ticket.type,
        tags: ticket.tags,
        sprintName: ticket.sprintName,
        projectId: ticket.projectId,
        projectName: ticket.projectName,
        hoursLeft: ticket.hourRemaining,
      };
      ticketCacheData["children"].push(ticketData);
      this.writeToCache("tickets", {
        data: ticketCacheData,
        expiration: Date.now(),
      });

      return ticketData;
    });

    const result = await Promise.allSettled([ticketPromise]);
    return result
      .filter((result) => result.status === "fulfilled")
      .map((result) => result.value)[0];
  }

  /**
   * Removes item from cache data.
   *
   * @param {string} item - The item to be removed from the cache.
   *
   * @return {void}
   */
  static removeFromCache(item) {
    localStorage.removeItem(item);
  }

  /**
   * Writes item to cache data.
   *
   * @param {string} item - The key to identify the data in the cache.
   * @param {*} data - The data to be stored in the cache.
   *
   * @returns {void}
   */
  static writeToCache(item, data) {
    localStorage.setItem(item, JSON.stringify(data));
  }

  /**
   * Retrieves item from cache.
   *
   * @param {string} item - The name of the item to read from the cache.
   * @return {any} - The value associated with the item, or null if item is not found.
   */
  static readFromCache(item) {
    return JSON.parse(localStorage.getItem(item)) || null;
  }

  /**
   * Retrieves data from cache based on the provided item.
   *
   * @param {any} item - The item used to retrieve data from cache.
   * @return {any|boolean} - The data retrieved from cache if it's not expired, or false if data is expired or not found.
   */
  static getCacheData(item) {
    const cacheData = this.readFromCache(item);

    if (!cacheData) {
      return false;
    }

    const cacheDataExpiration = cacheData.expiration ?? 0;
    // Convert minutes to ms
    const cacheTimeoutMs = this.cacheTimeouts[item] * 60000;
    const cacheDataExpired = Date.now() - cacheDataExpiration > cacheTimeoutMs;

    return cacheDataExpired ? false : cacheData.data;
  }

  /**
   * Retrieves all tickets from the LeanTime API.
   * @return {Promise} - Retrieved tickets or error message
   */
  static getAllTickets() {
    return this.callApi("leantime.rpc.tickets.getAll", {});
  }

  static getTicket(id) {
    return this.callApi("leantime.rpc.tickets.getTicket", { id: id });
  }

  /**
   * Sends a JSON-RPC POST request to the specified API endpoint.
   * @param {String} method - The name of the method to be called on the API.
   * @param {Object} params - The parameters to be sent to the API method.
   * @return {Promise} API response or error message
   */
  static callApi(method, params) {
    return new Promise((resolve, reject) => {
      jQuery.ajax({
        url: leantime.appUrl + "/api/jsonrpc/",
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        data: JSON.stringify({
          method: method,
          jsonrpc: "2.0",
          id: "1",
          params: params,
        }),
        success: resolve,
        error: reject,
      });
    });
  }

  /**
   * Retrieve ticket data from cache based on ticket ID
   * or re-syncs cache to retrieve it.
   *
   * @param {string} ticketId - The ID of the ticket to retrieve.
   * @return {object|null} - The ticket data if found in cache, otherwise null.
   */
  static getTicketDataFromCache(ticketId) {
    let cacheTickets = this.readFromCache("tickets");
    if (!cacheTickets) {
      this.fetchTicketData().then((availableTags) => {
        this.getTicketDataFromCache();
      });
      return;
    }
    cacheTickets = cacheTickets.data.children;
    let foundTicket = false;
    for (let i = 0; i < cacheTickets.length; i++) {
      if (cacheTickets[i].id === ticketId) {
        foundTicket = cacheTickets[i];
        break;
      }
    }
    return foundTicket;
  }
}
